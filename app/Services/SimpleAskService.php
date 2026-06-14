<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;


class SimpleAskService
{
    public const DEFAULT_MODEL = 'openai/gpt-4o-mini'; // Utilisation d'un vrai modèle

    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key') ?? '';
        $this->baseUrl = rtrim(config('services.openrouter.base_url') ?? 'https://openrouter.ai/api/v1', '/');
    }

    /**
     * Récupère la liste des modèles disponibles.
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     context_length: int,
     *     max_completion_tokens: int,
     *     input_modalities: array<string>,
     *     output_modalities: array<string>,
     *     supported_parameters: array<string>
     * }>
     */
    public function getModels(): array
    {
        return cache()->remember('openrouter.models', now()->addHour(), function (): array {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->baseUrl . '/models');

            return collect($response->json('data', []))
                ->sortBy('name')
                ->map(fn (array $model): array => [
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
                ->toArray()
            ;
        });
    }

    /**
     * Envoie un message et retourne la réponse du modèle.
     *
     * @param array<int, array{
     *     role: 'assistant'|'system'|'tool'|'user',
     *     content: array<int, array{
     *         type: 'image_url'|'text',
     *         text?: string,
     *         image_url?: array{url: string, detail?: string}
     *     }>|string
     * }> $messages
     */
    public function sendMessage(array $messages, ?string $model = null, float $temperature = 1.0): string
    {
        $model = $model ?? self::DEFAULT_MODEL;
        
        $questionCount = count(array_filter($messages, fn($m) => isset($m['role']) && $m['role'] === 'user'));
        $messages = [$this->getSystemPrompt($questionCount), ...$messages];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('app.url'),
            'X-Title' => config('app.name'),
        ])
            ->timeout(120)
            ->withOptions(['stream' => true])
            ->post($this->baseUrl . '/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'stream' => true,
                'include_reasoning' => true,
            ])
        ;

        // Gestion des erreurs
        if ($response->status() !== 200) {
            $body = $response->body();
            $json = json_decode($body, true);
            $error = $json['error']['message'] ?? "Status {$response->status()}";
            throw new \RuntimeException("Erreur API: {$error}");
        }

        $stream = $response->toPsrResponse()->getBody();
        $fullContent = '';
        $buffer = '';

        while (!$stream->eof()) {
            $buffer .= $stream->read(1024);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if (str_starts_with($line, 'data:')) {
                    $data = trim(substr($line, 5));
                    if ($data === '[DONE]') {
                        continue;
                    }

                    $json = json_decode($data, true);
                    if ($json && isset($json['choices'][0]['delta'])) {
                        $delta = $json['choices'][0]['delta'];
                        
                        if (!empty($delta['reasoning'])) {
                            $chunk = "[REASONING]" . $delta['reasoning'] . "[/REASONING]";
                            echo $chunk;
                            ob_flush();
                            flush();
                            $fullContent .= $chunk;
                        }
                        
                        if (isset($delta['content']) && $delta['content'] !== null && $delta['content'] !== '') {
                            $chunk = $delta['content'];
                            echo $chunk;
                            ob_flush();
                            flush();
                            $fullContent .= $chunk;
                        }
                    }
                }
            }
        }

        return $fullContent;
    }

    /**
     * Retourne le prompt système.
     *
     * @return array{role: 'system', content: string}
     */
    private function getSystemPrompt(int $questionCount = 0): array
    {
        $user = Auth::user()?->name ?? 'l\'utilisateur';
        $now = now()->locale('fr')->format('l d F Y H:i');

        $basePrompt = view('prompts.system', [
            'now' => $now,
            'user' => $user,
        ])->render();

        $instruction = "\n\n[DIRECTIVE SYSTÈME STRICTE ET PRIORITAIRE]\n"
            . "Tu es un oracle mystique. Tu t'endors UNIQUEMENT APRÈS 7 QUESTIONS. Ne t'endors surtout pas avant !\n"
            . "Dès ton premier message, explique à l'utilisateur que tu lis l'avenir dans des shots d'alcool.\n"
            . "Tu prends un shot d'alcool fort pour chaque question posée. "
            . "À la fin de chaque réponse, au moment de prendre ton shot, tu DOIS textuellement trinquer en disant 'Santé !'.\n"
            . "Actuellement, tu as bu {$questionCount} shot(s).\n"
            . "Plus ce nombre de shots augmente, plus tu es IVRE (bourré), tu as le hoquet (*hic*), tu bégaies, "
            . "tu as du mal à articuler et tes prédictions sont un mélange hilarant de visions grandioses et de bêtises de comptoir.\n";

        if ($questionCount > 7) {
            $instruction .= "ATTENTION : Tu as dépassé les 7 shots. Tu es ivre mort et tu t'endors d'un coup ! "
                . "Ta seule réponse doit être des ronflements (*Zzzz... Rrrr...*), tu ne réponds plus à rien d'autre.";
        } else {
            $instruction .= "Tu n'es pas encore endormi, ton niveau d'ivresse est de {$questionCount}/7. Garde ton personnage ! NE T'ENDORS PAS ENCORE !";
        }

        return [
            'role' => 'system',
            'content' => $basePrompt . $instruction,
        ];
    }
}