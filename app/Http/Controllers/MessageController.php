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

        // Génération automatique du titre s'il s'agit du tout premier message
        if (empty($conversation->title)) {
            $conversation->update([
                'title' => $streamService->generateTitle($request->message)
            ]);
        }

        // Vérification de l'état de l'oracle (s'endort après 7 réponses)
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
        $answer = ''; // Initialize answer variable

        return response()->stream(function () use ($messages, $conversation, $streamService, $assistantMessagesCount) {
            // 🚀 ÉTAPE 1 : Désactiver complètement les tampons de sortie PHP/Laravel
            session_write_close(); // Libère la session pour ne pas bloquer les requêtes
            ini_set('zlib.output_compression', '0');
            ini_set('implicit_flush', '1');
            ob_implicit_flush(true);
            if (function_exists('apache_setenv')) {
                apache_setenv('no-gzip', '1');
            }
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $answer = $streamService->streamToOutput($messages, $conversation->model, 1.0, null, false, $assistantMessagesCount);

            // 4. Sauvegarde réponse IA une fois le stream terminé
            if (!empty($answer)) {
                Message::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $answer,
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8', // Mieux que text/plain pour forcer le proxy
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
            'Content-Encoding' => 'none', // 🚀 ÉTAPE 2 : Empêche Apache (Laragon) de compresser et bloquer le stream
        ]);
    }
}