<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
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
        $this->apiKey = config('services.openrouter.api_key') ?? '';
        $this->baseUrl = rtrim(config('services.openrouter.base_url', 'https://openrouter.ai/api/v1') ?? 'https://openrouter.ai/api/v1', '/');
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
     * Stream un message en temps réel vers la sortie.
     * Output le contenu texte directement (compatible avec useStream de Laravel).
     */
    public function streamToOutput(
        array $messages,
        ?string $model = null,
        float $temperature = 1.0,
        ?string $reasoningEffort = null,
        ?User $user = null
    ): string {
        $response = $this->sendStreamRequest($messages, $model, $temperature, $reasoningEffort, $user);

        if ($response->failed()) {
            echo "[ERROR] " . $response->json('error.message', 'HTTP Error');
            $this->flush();
            return "";
        }

        $fullResponse = "";

        foreach ($this->parseSSEStream($response->toPsrResponse()->getBody()) as $event) {
            if ($event['type'] === 'error') {
                echo "[ERROR] " . $event['data'];
                $this->flush();
                return $fullResponse;
            }

            if ($event['type'] === 'content' && $event['data']) {
                echo $event['data'];
                $fullResponse .= $event['data'];
                $this->flush();
            }

            // Pour le reasoning, on utilise un préfixe spécial
            if ($event['type'] === 'reasoning' && $event['data']) {
                echo "[REASONING]" . $event['data'] . "[/REASONING]";
                $fullResponse .= "[REASONING]" . $event['data'] . "[/REASONING]";
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
        ?User $user
    ): \Illuminate\Http\Client\Response {
        $payload = [
            'model' => $model ?? self::DEFAULT_MODEL,
            'messages' => [$this->getSystemPrompt($user), ...$messages],
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

        if (!str_starts_with($line, 'data: ')) {
            return null;
        }

        $data = substr($line, 6);

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

            if (!empty($delta['content'])) {
                return ['type' => 'content', 'data' => $delta['content']];
            }

            if (!empty($delta['reasoning'])) {
                return ['type' => 'reasoning', 'data' => $delta['reasoning']];
            }

            if (!empty($delta['reasoning_content'])) {
                return ['type' => 'reasoning', 'data' => $delta['reasoning_content']];
            }

            return null;
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * Retourne le prompt système.
     *
     * @param User|null $user
     * @return array{role: 'system', content: string}
     */
    private function getSystemPrompt(?User $user): array
    {
        $instructions = "";
        
        if ($user && $user->settings) {
            $settings = is_string($user->settings) ? json_decode($user->settings, true) : $user->settings;
            if (is_array($settings)) {
                $instructions = "\n\n[DIRECTIVES DE L'UTILISATEUR]\nRespecte scrupuleusement ces paramètres :\n";
                foreach ($settings as $key => $value) {
                    if (!empty($value)) {
                        $instructions .= "- " . ucfirst(str_replace('_', ' ', $key)) . " : " . $value . "\n";
                    }
                }
            }
        }

        return [
            'role' => 'system',
            'content' => view('prompts.system', [
                'now' => now()->locale('fr')->format('l d F Y H:i'),
                'user' => $user?->name ?? 'l\'utilisateur',
            ])->render() . $instructions,
        ];
    }
}