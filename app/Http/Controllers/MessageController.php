<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\SimpleAskService;
use App\Services\SimpleAskStreamService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function store(
        Request $request,
        Conversation $conversation,
        SimpleAskService $service,
        SimpleAskStreamService $streamService
    ) {
        $request->validate([
            'message' => 'required|string'
        ]);

        // 1. Sauvegarde message user
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $request->message,
        ]);

        // Vérification de l'état de l'oracle (s'endort après 3 réponses)
        $assistantMessagesCount = $conversation->messages()->where('role', 'assistant')->count();
        
        if ($assistantMessagesCount >= 7) {
            $sleepMessage = "*(L'oracle ronfle bruyamment)* Zzz... *hic*... J'vois flou là... J'ai plus soif... repassez d'main... Zzz...";

            Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $sleepMessage,
            ]);

            // Retourne un stream direct pour ne pas casser le JS côté front
            return response()->stream(function () use ($sleepMessage) {
                echo $sleepMessage;
                flush();
            }, 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-cache, no-store',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        // 2. Préparer historique pour l'IA
        $messages = $conversation->messages()
            ->orderBy('id')
            ->get()
            ->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content
            ])
            ->toArray();

        // 3. Appel IA via Stream
        return response()->stream(
            function () use ($messages, $conversation, $request, $service, $streamService) {
                // 🚀 ÉTAPE 1 : Désactiver complètement les tampons de sortie PHP/Laravel
                while (ob_get_level() > 0) {
                    ob_end_flush();
                }

                $answer = $streamService->streamToOutput(
                    $messages,
                    $conversation->model,
                    1.0,
                    null,
                    $request->user()
                );

                // 4. Sauvegarde réponse IA une fois le stream terminé
                Message::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $answer,
                ]);

                // 5. Génération du titre si première réponse (synchronement juste après la fin du stream visuel)
                if (!$conversation->title && $conversation->messages()->count() <= 2) {
                    $title = $service->sendMessage([
                        [
                            'role' => 'user',
                            'content' => "Donne un titre mystique et court (max 5 mots) comme un oracle : " . $request->message
                        ]
                    ]);

                    $conversation->update(['title' => $title]);
                }
            },
            200,
            [
                'Content-Type' => 'text/event-stream; charset=utf-8', // Mieux que text/plain pour forcer le proxy
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'X-Accel-Buffering' => 'no',
                'Content-Encoding' => 'none', // 🚀 ÉTAPE 2 : Empêche Apache (Laragon) de compresser et bloquer le stream
            ]
        );
    }
}