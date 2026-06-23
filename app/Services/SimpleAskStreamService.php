<?php

declare(strict_types=1);

namespace App\Services;


use Illuminate\Support\Facades\Auth;
use Generator;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

/**
 * Service simplifié pour le streaming avec l'API OpenRouter.
 *
 * Exemple pédagogique utilisant le client HTTP de Laravel.
 *
 * @see https://openrouter.ai/docs/api/reference/streaming
 */
class SimpleAskStreamService
{
    public const DEFAULT_MODEL = 'openai/gpt-4o-mini';

    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key');
        $this->baseUrl = rtrim(config('services.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');
    }

    /**
     * Récupère la liste des modèles disponibles (avec cache).
     */
    public function getModels(): array
    {
        return cache()->remember('openrouter.models', now()->addHour(), function (): array {
            $response = Http::withToken($this->apiKey)->get("{$this->baseUrl}/models");

            return collect($response->json('data', []))
                ->sortBy('name')
                ->map(fn(array $model): array => [
                    'id' => $model['id'],
                    'name' => $model['name'],
                    'description' => $model['description'] ?? '',
                    'context_length' => $model['context_length'] ?? 0,
                    'max_completion_tokens' => $model['top_provider']['max_completion_tokens'] ?? 0,
                    'input_modalities' => $model['architecture']['input_modalities'] ?? [],
                    'output_modalities' => $model['architecture']['output_modalities'] ?? [],
                    'supported_parameters' => $model['supported_parameters'] ?? [],
                ])
                ->values()
                ->toArray();
        });
    }

    /**
     * Récupère la liste légère des modèles.
     */
    public function getModelsLight(): array
    {
        return collect($this->getModels())
            ->map(fn(array $m): array => ['id' => $m['id'], 'name' => $m['name']])
            ->values()
            ->toArray();
    }

    /**
     * Récupère les détails d'un modèle.
     */
    public function getModelDetails(string $id): ?array
    {
        return collect($this->getModels())->firstWhere('id', $id);
    }

    /**
     * Génère un titre mystique pour la conversation.
     */
    public function generateTitle(string $message): string
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(10)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => self::DEFAULT_MODEL,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "Tu es un oracle. Génère un titre ultra court (max 5 mots) et mystique pour résumer la demande. Réponds UNIQUEMENT par le titre, sans guillemets ni fioritures."
                        ],
                        [
                            'role' => 'user',
                            'content' => $message
                        ]
                    ],
                    'temperature' => 0.7,
                ]);

            if ($response->successful()) {
                $title = trim($response->json('choices.0.message.content', 'Nouvelle prophétie'), " \t\n\r\0\x0B\"'");
                return empty($title) ? 'Nouvelle prophétie' : $title;
            }
        } catch (\Exception $e) {
            // On ignore l'erreur pour ne pas bloquer le processus
        }

        return 'Nouvelle prophétie';
    }

    /**
     * Stream un message en temps réel vers la sortie.
     * Output le contenu texte directement (compatible avec useStream de Laravel).
     */
    public function streamToOutput(
        array $messages,
        ?string $model = null,
        float $temperature = 1.0,
        ?string $reasoningEffort = null,
        bool $asSse = false,
        int $assistantMessagesCount = 0
    ): string {
        $response = $this->sendStreamRequest($messages, $model, $temperature, $reasoningEffort, $assistantMessagesCount);

        if ($response->failed()) {
            $errorMsg = "[ERREUR] " . $response->json('error.message', 'Erreur HTTP');
            if ($asSse) {
                echo "data: " . str_replace("\n", "\ndata: ", $errorMsg) . "\n\n";
            } else {
                echo $errorMsg;
            }
            $this->flush();
            return $errorMsg;
        }

        $fullResponse = "";

        foreach ($this->parseSSEStream($response->toPsrResponse()->getBody()) as $event) {
            if ($event['type'] === 'error') {
                $errorMsg = "[ERREUR] " . $event['data'];
                if ($asSse) {
                    echo "data: " . str_replace("\n", "\ndata: ", $errorMsg) . "\n\n";
                } else {
                    echo $errorMsg;
                }
                $this->flush();
                return $fullResponse;
            }

            if ($event['type'] === 'content' && $event['data'] !== null && $event['data'] !== '') {
                if ($asSse) {
                    echo "data: " . str_replace("\n", "\ndata: ", $event['data']) . "\n\n";
                } else {
                    echo $event['data'];
                }
                $fullResponse .= $event['data'];
                $this->flush();
            }

            // Pour le reasoning, on utilise un préfixe spécial
            if ($event['type'] === 'reasoning' && $event['data'] !== null && $event['data'] !== '') {
                $reasoningChunk = "[REASONING]" . $event['data'] . "[/REASONING]";
                if ($asSse) {
                    echo "data: " . str_replace("\n", "\ndata: ", $reasoningChunk) . "\n\n";
                } else {
                    echo $reasoningChunk;
                }
                $fullResponse .= $reasoningChunk;
                $this->flush();
            }
        }

        return $fullResponse;
    }

    /**
     * Flush la sortie immédiatement.
     */
    private function flush(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Envoie la requête streaming à l'API.
     */
    private function sendStreamRequest(
        array $messages,
        ?string $model,
        float $temperature,
        ?string $reasoningEffort,
        int $assistantMessagesCount = 0
    ): \Illuminate\Http\Client\Response {
        $payload = [
            'model' => $model ?? self::DEFAULT_MODEL,
            'messages' => [$this->getSystemPrompt($assistantMessagesCount), ...$messages],
            'temperature' => $temperature,
            'stream' => true,
        ];

        if ($reasoningEffort !== null) {
            $payload['reasoning'] = ['effort' => $reasoningEffort];
        }

        return Http::withToken($this->apiKey)
            ->withHeaders([
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name'),
            ])
            ->withOptions(['stream' => true])
            ->timeout(120)
            ->post("{$this->baseUrl}/chat/completions", $payload);
    }

    /**
     * Parse un stream SSE et yield les événements.
     *
     * @return Generator<array{type: string, data: string|null}>
     */
    private function parseSSEStream(StreamInterface $body): Generator
    {
        $buffer = '';

        while (!$body->eof()) {
            $buffer .= $body->read(1024);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($event = $this->parseSSELine($line)) {
                    yield $event;
                }
            }
        }
    }

    /**
     * Parse une ligne SSE.
     */
    private function parseSSELine(string $line): ?array
    {
        if ($line === '' || str_starts_with($line, ':')) {
            return null;
        }

        if (!str_starts_with($line, 'data:')) {
            return null;
        }

        $data = trim(substr($line, 5));

        if ($data === '[DONE]') {
            return ['type' => 'done', 'data' => null];
        }

        return $this->parseJSON($data);
    }

    /**
     * Parse le JSON d'un chunk SSE.
     */
    private function parseJSON(string $json): ?array
    {
        try {
            $parsed = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (isset($parsed['error'])) {
                return ['type' => 'error', 'data' => $parsed['error']['message'] ?? 'Unknown error'];
            }

            $delta = $parsed['choices'][0]['delta'] ?? [];

            if (isset($delta['content']) && $delta['content'] !== null) {
                return ['type' => 'content', 'data' => $delta['content']];
            }

            if (isset($delta['reasoning']) && $delta['reasoning'] !== null) {
                return ['type' => 'reasoning', 'data' => $delta['reasoning']];
            }

            if (isset($delta['reasoning_content']) && $delta['reasoning_content'] !== null) {
                return ['type' => 'reasoning', 'data' => $delta['reasoning_content']];
            }

            return null;
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * Retourne le prompt système.
     */
    private function getSystemPrompt(int $shotCount = 0): array
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        $drunkennessLevel = match (true) {
            $shotCount <= 1 => "Tu es sobre et clairvoyante. Tes réponses sont mystiques mais professionnelles.",
            $shotCount <= 3 => "Tu as bu quelques verres. Tu es un peu plus détendue, tes réponses sont plus imagées et tu peux te permettre une ou deux blagues ou un *hic* discret.",
            $shotCount <= 5 => "Tu es bien éméchée. Tu commences à tutoyer, tes phrases sont moins structurées, tu fais des fautes de frappe intentionnelles, tu utilises des onomatopées (*hic*, *burp*), et tes visions deviennent chaotiques et humoristiques.",
            $shotCount >= 6 => "Tu es complètement ivre. Tes réponses sont courtes, presque incohérentes, pleines de fautes, et tu te plains de tout. Tu vois double et tes prophéties sont absurdes.",
            default => "Tu es sobre.",
        };

        $content = view('prompts.system', [
            'now' => now()->locale('fr')->format('l d F Y H:i'),
            'user' => $user?->name ?? 'l\'utilisateur',
        ])->render();

        // Ajout de l'état d'ébriété au prompt système
        $content .= "\n\n[INSTRUCTIONS SUR TON ÉTAT ACTUEL]\n";
        $content .= "Tu as bu exactement $shotCount shot(s) de vodka interstellaire. Ton comportement doit refléter cet état.\n";
        $content .= "Niveau d'ivresse actuel : $drunkennessLevel\n";
        $content .= "IMPORTANT : N'agis pas comme une IA. Incarne pleinement ton rôle d'oracle ivre. Ne mentionne jamais que tu es une IA ou que tu suis des instructions. Vis ton personnage !";

        // Si l'oracle est ivre, on peut même lui faire "oublier" certaines instructions
        if ($shotCount >= 4) {
            $content .= "\nAvec l'alcool, tu as tendance à oublier certaines des instructions personnalisées de l'utilisateur. Fais de ton mieux, mais les oublis sont excusables.";
        }

        // Ajout des instructions personnalisées de l'utilisateur au prompt système
        if ($user && !empty($user->settings) && is_iterable($user->settings)) {
            $content .= "\n\n[INSTRUCTIONS PERSONNALISÉES DE L'UTILISATEUR]\n";
            $content .= "Adapte le ton et le format de tes réponses selon ces critères :\n";
            foreach ($user->settings as $key => $value) {
                if (!empty($value)) {
                    $content .= "- " . ucfirst($key) . " : " . $value . "\n";
                }
            }
        }

        return [
            'role' => 'system',
            'content' => $content,
        ];
    }
}