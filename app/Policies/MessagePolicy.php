<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    /**
     * Détermine si l'utilisateur peut voir les messages d'une conversation.
     */
    public function viewAny(User $user, Conversation $conversation): bool
    {
        return $user->id === $conversation->user_id;
    }

    /**
     * Détermine si l'utilisateur peut voir un message spécifique.
     */
    public function view(User $user, Message $message): bool
    {
        return $user->id === $message->conversation->user_id;
    }

    /**
     * Détermine si l'utilisateur peut créer un message dans une conversation.
     */
    public function create(User $user, Conversation $conversation): bool
    {
        return $user->id === $conversation->user_id;
    }

    /**
     * Détermine si l'utilisateur peut mettre à jour un message.
     */
    public function update(User $user, Message $message): bool
    {
        return $user->id === $message->conversation->user_id;
    }

    /**
     * Détermine si l'utilisateur peut supprimer un message.
     */
    public function delete(User $user, Message $message): bool
    {
        return $user->id === $message->conversation->user_id;
    }
}