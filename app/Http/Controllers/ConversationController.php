<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\SimpleAskService;

class ConversationController extends Controller
{
    public function index(Request $request, SimpleAskService $simpleAskService)
    {
        $this->authorize('viewAny', Conversation::class);

        /** @var \App\Models\User $user */
        $user = $request->user();

        return Inertia::render('Chat/Index', [

            'conversations' => Conversation::query()
                ->where('user_id', $user->id)
                ->with('tags')
                ->latest()
                ->get(),

            'conversation' => null,

            'models' => $simpleAskService->getModels(),

            'tags' => Tag::query()
                ->where('user_id', $user->id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(
        Request $request,
        Conversation $conversation,
        SimpleAskService $simpleAskService
    ) {
        $this->authorize('view', $conversation);

        /** @var \App\Models\User $user */
        $user = $request->user();

        return Inertia::render('Chat/Index', [

            'conversations' => Conversation::query()
                ->where('user_id', $user->id)
                ->with('tags')
                ->latest()
                ->get(),

            'conversation' =>
                $conversation->load('messages', 'tags'),

            'models' => $simpleAskService->getModels(),

            'tags' => Tag::query()
                ->where('user_id', $user->id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Conversation::class);

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
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return redirect('/chat')->with('toast', [
            'type' => 'success', 
            'message' => 'Conversation supprimée avec succès.'
        ]);
    }

    public function fork(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Créer une nouvelle conversation avec le même modèle
        $forked = Conversation::query()->create([
            'user_id' => $user->id,
            'model' => $conversation->model,
            'title' => $conversation->title ? "{$conversation->title} (copie)" : null,
        ]);

        // Copier tous les messages de la conversation source
        $conversation->messages()
            ->orderBy('id')
            ->get()
            ->each(function ($message) use ($forked) {
                \App\Models\Message::create([
                    'conversation_id' => $forked->id,
                    'role' => $message->role,
                    'content' => $message->content,
                ]);
            });

        // Copier les tags de la conversation source
        $forked->tags()->sync($conversation->tags()->pluck('tags.id'));

        return redirect("/chat/{$forked->id}")->with('toast', [
            'type' => 'success',
            'message' => 'Conversation dupliquée avec succès.',
        ]);
    }

    public function syncTags(Request $request, Conversation $conversation)
    {
        $this->authorize('update', $conversation);

        $request->validate([
            'tags' => 'array',
            'tags.*' => 'string|max:50',
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $tagIds = collect($request->input('tags', []))
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->map(function (string $name) use ($user): int {
                return Tag::firstOrCreate([
                    'user_id' => $user->id,
                    'name' => mb_strtolower($name),
                ])->id;
            })
            ->values()
            ->all();

        $conversation->tags()->sync($tagIds);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Tags mis à jour avec succès.',
        ]);
    }

}
