<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Détermine si l'utilisateur peut voir un profil utilisateur.
     */
    public function view(User $currentUser, User $user): bool
    {
        return $currentUser->id === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut mettre à jour un profil utilisateur.
     */
    public function update(User $currentUser, User $user): bool
    {
        return $currentUser->id === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut supprimer un compte utilisateur.
     */
    public function delete(User $currentUser, User $user): bool
    {
        return $currentUser->id === $user->id;
    }
}