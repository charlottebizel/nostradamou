<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SimpleAskStreamService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller pour la démonstration du streaming SSE.
 *
 * Exemple pédagogique : streaming temps réel avec Laravel + Vue.
 */
class AskStreamController extends Controller
{
    public function __construct(
        private SimpleAskStreamService $streamService
    ) {}

    /**
     * Affiche la page de streaming.
     */
    public function index(Request $request): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        $modelId = $request->input('model')
            ?? $user?->preferred_model
            ?? SimpleAskStreamService::DEFAULT_MODEL;

        return Inertia::render('AskStream/Index', [
            'models' => $this->streamService->getModelsLight(),
            'selectedModel' => $modelId,
            'selectedModelDetails' => fn() => $this->streamService->getModelDetails($modelId),
        ]);
    }

    /**
     * Endpoint de streaming.
     */
    public function stream(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:100000',
            'model' => 'required|string',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'reasoning_effort' => 'nullable|string|in:low,medium,high',
        ]);

        // Update user's preferred model
        /** @var \App\Models\User|null $user */
        $user = $request->user();
        if ($user && $user->preferred_model !== $validated['model']) {
            $user->update(['preferred_model' => $validated['model']]);
        }

        $messages = [['role' => 'user', 'content' => $validated['message']]];
        $model = $validated['model'];
        $temperature = (float) ($validated['temperature'] ?? 1.0);
        $reasoningEffort = $validated['reasoning_effort'] ?? null;

        return response()->stream(
            function () use ($messages, $model, $temperature, $reasoningEffort): void {
                // Désactiver complètement les tampons de sortie PHP/Laravel
                session_write_close();
                ini_set('zlib.output_compression', '0');
                ini_set('implicit_flush', '1');
                ob_implicit_flush(true);
                if (function_exists('apache_setenv')) apache_setenv('no-gzip', '1');

                while (ob_get_level() > 0) {
                    ob_end_flush();
                }
                $this->streamService->streamToOutput($messages, $model, $temperature, $reasoningEffort, true);
            },
            200,
            [
                'Content-Type' => 'text/event-stream; charset=utf-8',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'X-Accel-Buffering' => 'no',
                'Content-Encoding' => 'none',
            ]
        );
    }
}