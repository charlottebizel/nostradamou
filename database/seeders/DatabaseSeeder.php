<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Conversation;
use App\Models\Message;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Création d'une conversation de test
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Test : La conversation des 8 shots',
            'model' => 'openai/gpt-4o-mini',
        ]);

        $questions = [
            "Bonjour Nostradamou, peux-tu me prédire mon avenir ?",
            "Vais-je trouver l'amour ?",
            "Aurai-je une promotion bientôt ?",
            "Est-ce que je vais gagner au loto ?",
            "Comment sera la météo demain ?",
            "Quel est le sens de la vie ?",
            "As-tu un conseil pour moi ?",
            "Une dernière question, comment te sens-tu ?"
        ];

        $answers = [
            "Ah, mon ami... *hic* Laisse-moi regarder dans mon shot. Ton avenir est... flou, mais glorieux ! Santé !",
            "L'amour ? C'est comme la tequila, ça pique au début mais après on en redemande... *hic* Santé !",
            "Une promotion... *hic* Oui, tu vas devenir le roi de l'open space ! Mais n'oublie pas de trinquer. Santé !",
            "Le loto... les numéros sont 4, 8, 15, 16... *hic* et puis je sais plus. Santé !",
            "Il va pleuvoir... *hic* de la bière ! Prépare ton parapluie à l'envers ! Santé !",
            "Le sens de la vie ? C'est 42 shots de vodka, mon vieux ! *hic* Santé !",
            "Mon conseil... *hic* Ne mélange jamais le vin et les spiritueux. Santé !",
            "*Zzzz... Rrrr...*"
        ];

        for ($i = 0; $i < 8; $i++) {
            Message::create([
                'conversation_id' => $conversation->id, 
                'role' => 'user', 
                'content' => $questions[$i]
            ]);
            
            Message::create([
                'conversation_id' => $conversation->id, 
                'role' => 'assistant', 
                'content' => $answers[$i]
            ]);
        }
    }
}
