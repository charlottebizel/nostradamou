<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Met à jour le modèle IA préféré de l'utilisateur et synchronise la conversation.
     */
    public function updateModel(Request $request)
    {
        $this->authorize('update', $request->user());

        $request->validate(['model' => 'required|string']);

        $request->user()->update(['preferred_model' => $request->model]);

        if ($request->conversation_id) {
            Conversation::where('id', $request->conversation_id)
                ->where('user_id', $request->user()->id)
                ->update(['model' => $request->model]);
        }

        return back();
    }

    /**
     * Met à jour les instructions personnalisées de l'utilisateur.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorize('update', $request->user());

        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'profession' => 'nullable|string|max:255',
            'interests' => 'nullable|string|max:255',
            'expertise_level' => 'nullable|string',
            'goals' => 'nullable|string|max:255',
            'tone' => 'nullable|string',
            'format' => 'nullable|string',
            'length' => 'nullable|string',
            'explanation_style' => 'nullable|string',
        ]);

        $user->update(['settings' => $validated]);

        return back();
    }
}
