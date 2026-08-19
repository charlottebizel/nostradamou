<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tag;
use App\Models\User;
use App\Services\SimpleAskService;

test('un utilisateur authentifié peut voir la liste de ses conversations', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'model' => SimpleAskService::DEFAULT_MODEL,
    ]);

    $otherConversation = Conversation::create([
        'user_id' => $otherUser->id,
        'model' => SimpleAskService::DEFAULT_MODEL,
    ]);

    $this->actingAs($user)
        ->get(route('chat.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Chat/Index')
            ->has('conversations', 1)
            ->where('conversations.0.id', $conversation->id)
            ->where('conversation', null)
        );
});

test('un utilisateur peut voir une conversation avec ses messages', function () {
    $user = User::factory()->create();

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'model' => SimpleAskService::DEFAULT_MODEL,
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Bienvenue !',
    ]);

    $this->actingAs($user)
        ->get(route('chat.show', $conversation))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Chat/Index')
            ->where('conversation.id', $conversation->id)
            ->has('conversation.messages', 1)
            ->where('conversation.messages.0.content', 'Bienvenue !')
        );
});

test('un utilisateur ne peut pas voir la conversation d\'un autre utilisateur', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $conversation = Conversation::create([
        'user_id' => $otherUser->id,
        'model' => SimpleAskService::DEFAULT_MODEL,
    ]);

    $this->actingAs($user)
        ->get(route('chat.show', $conversation))
        ->assertForbidden();
});

test('un utilisateur peut créer une conversation avec le message d\'accueil', function () {
    $user = User::factory()->create([
        'preferred_model' => 'openai/gpt-4o-mini',
    ]);

    $this->actingAs($user)
        ->post(route('chat.store'))
        ->assertRedirect();

    $conversation = Conversation::first();
    expect($conversation)->not->toBeNull()
        ->and($conversation->user_id)->toBe($user->id)
        ->and($conversation->model)->toBe('openai/gpt-4o-mini');

    $welcomeMessage = Message::where('conversation_id', $conversation->id)->first();
    expect($welcomeMessage)->not->toBeNull()
        ->and($welcomeMessage->role)->toBe('assistant')
        ->and($welcomeMessage->content)->toContain('Commençons');
});

test('un utilisateur peut supprimer sa propre conversation', function () {
    $user = User::factory()->create();

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'model' => SimpleAskService::DEFAULT_MODEL,
    ]);

    $this->actingAs($user)
        ->delete(route('chat.destroy', $conversation))
        ->assertRedirect(route('chat.index'))
        ->assertSessionHas('toast');

    expect(Conversation::find($conversation->id))->toBeNull();
});

test('un utilisateur ne peut pas supprimer la conversation d\'un autre utilisateur', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $conversation = Conversation::create([
        'user_id' => $otherUser->id,
        'model' => SimpleAskService::DEFAULT_MODEL,
    ]);

    $this->actingAs($user)
        ->delete(route('chat.destroy', $conversation))
        ->assertForbidden();

    expect(Conversation::find($conversation->id))->not->toBeNull();
});

test('les invités sont redirigés vers la page de connexion', function () {
    $this->get(route('chat.index'))->assertRedirect(route('login'));
    $this->post(route('chat.store'))->assertRedirect(route('login'));
});

test('un utilisateur peut synchroniser les tags d\'une conversation', function () {
    $user = User::factory()->create();

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'model' => SimpleAskService::DEFAULT_MODEL,
    ]);

    $this->actingAs($user)
        ->post(route('chat.tags.sync', $conversation), [
            'tags' => ['Urgent', 'Projet', 'urgent'],
        ])
        ->assertRedirect()
        ->assertSessionHas('toast');

    expect($conversation->tags()->count())->toBe(2)
        ->and($conversation->tags()->pluck('name')->sort()->values()->all())
        ->toBe(['projet', 'urgent']);
});

test('un utilisateur ne peut pas synchroniser les tags d\'une conversation d\'un autre utilisateur', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $conversation = Conversation::create([
        'user_id' => $otherUser->id,
        'model' => SimpleAskService::DEFAULT_MODEL,
    ]);

    $this->actingAs($user)
        ->post(route('chat.tags.sync', $conversation), [
            'tags' => ['Urgent'],
        ])
        ->assertForbidden();

    expect($conversation->tags()->count())->toBe(0);
});

test('les tags sont chargés avec les conversations', function () {
    $user = User::factory()->create();

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'model' => SimpleAskService::DEFAULT_MODEL,
    ]);

    $tag = Tag::create([
        'user_id' => $user->id,
        'name' => 'Important',
    ]);

    $conversation->tags()->attach($tag);

    $this->actingAs($user)
        ->get(route('chat.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Chat/Index')
            ->has('conversations.0.tags', 1)
            ->where('conversations.0.tags.0.name', 'Important')
            ->has('tags', 1)
            ->where('tags.0.name', 'Important')
        );
});

test('un utilisateur peut dupliquer (fork) une conversation avec ses messages et tags', function () {
    $user = User::factory()->create();

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'model' => SimpleAskService::DEFAULT_MODEL,
        'title' => 'Ma conversation',
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Question 1',
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Réponse 1',
    ]);

    $tag = Tag::create([
        'user_id' => $user->id,
        'name' => 'important',
    ]);

    $conversation->tags()->attach($tag);

    $this->actingAs($user)
        ->post(route('chat.fork', $conversation))
        ->assertRedirect()
        ->assertSessionHas('toast');

    $forked = Conversation::where('id', '!=', $conversation->id)->first();
    expect($forked)->not->toBeNull()
        ->and($forked->user_id)->toBe($user->id)
        ->and($forked->model)->toBe(SimpleAskService::DEFAULT_MODEL)
        ->and($forked->title)->toBe('Ma conversation (copie)')
        ->and($forked->messages()->count())->toBe(2)
        ->and($forked->messages()->pluck('content')->all())
        ->toBe(['Question 1', 'Réponse 1'])
        ->and($forked->tags()->count())->toBe(1)
        ->and($forked->tags()->first()->name)->toBe('important');
});

test('un utilisateur ne peut pas dupliquer (fork) la conversation d\'un autre utilisateur', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $conversation = Conversation::create([
        'user_id' => $otherUser->id,
        'model' => SimpleAskService::DEFAULT_MODEL,
    ]);

    $this->actingAs($user)
        ->post(route('chat.fork', $conversation))
        ->assertForbidden();

    expect(Conversation::count())->toBe(1);
});
