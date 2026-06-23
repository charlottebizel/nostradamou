<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\SimpleAskService;

class ConversationController extends Controller
{
    public function index(Request $request, SimpleAskService $simpleAskService)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return Inertia::render('Chat/Index', [

            'conversations' => Conversation::query()
                ->where('user_id', $user->id)
                ->latest()
                ->get(),

            'conversation' => null,

            'models' => $simpleAskService->getModels(),
        ]);
    }

    public function show(
        Request $request,
        Conversation $conversation,
        SimpleAskService $simpleAskService
    ) {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return Inertia::render('Chat/Index', [

            'conversations' => Conversation::query()
                ->where('user_id', $user->id)
                ->latest()
                ->get(),

            'conversation' =>
                $conversation->load('messages'),

            'models' => $simpleAskService->getModels(),
        ]);
    }

    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        /** @var \App\Models\Conversation $conversation */
        $conversation = Conversation::query()->create([

            'user_id' => $user->id,

            'model' => $user->preferred_model
                ?? SimpleAskService::DEFAULT_MODEL,

        ]);

        // Ajout du message d'accueil de l'oracle
        \App\Models\Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => "Commençons... *hic*",
        ]);
        return redirect(
            "/chat/{$conversation->id}"
        );
    }

    public function destroy(Request $request, \App\Models\Conversation $conversation)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($conversation->user_id !== $user->id) {
            abort(403);
        }

        $conversation->delete();

        return redirect('/chat')->with('toast', [
            'type' => 'success', 
            'message' => 'Conversation supprimée avec succès.'
        ]);
    }

}