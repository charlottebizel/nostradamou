# 🍸 Nostradamou

**L'oracle mystique qui prédit l'avenir dans des shots d'alcool.**

Nostradamou est une application web de chat IA immersive où un oracle mystique répond à vos questions. Plus vous posez de questions, plus l'oracle devient ivre — jusqu'à s'endormir après 7 shots !

## ✨ Fonctionnalités

### 🤖 Chat IA immersif
- **Oracle mystique** : un personnage IA qui prend un shot d'alcool à chaque question
- **Progression d'ivresse** : de sobre à ivre mort en 7 questions, avec bégaiement, hoquet et visions de comptoir
- **Endormissement** : après 7 questions, l'oracle s'endort et ne répond plus que par des ronflements
- **Streaming temps réel** : réponses affichées en direct via SSE (Server-Sent Events)

### 💬 Conversations
- **Création de conversations** : chaque conversation démarre avec un message d'accueil de l'oracle
- **Historique complet** : toutes les questions et réponses sont sauvegardées
- **Titres automatiques** : génération automatique du titre à partir du premier message
- **Tags (N-N)** : organisez vos conversations avec des tags personnalisés
- **Suppression** : supprimez vos conversations à tout moment

### 🏷️ Tags de conversations
- **Relation N-N** : une conversation peut avoir plusieurs tags, un tag peut être sur plusieurs conversations
- **Création automatique** : les tags sont créés automatiquement lors de la synchronisation
- **Normalisation** : les noms de tags sont insensibles à la casse (stockés en minuscules)
- **Isolation par utilisateur** : chaque utilisateur a ses propres tags

### 🎨 Personnalisation
- **Modèle IA préféré** : choisissez votre modèle OpenRouter par défaut
- **Instructions personnalisées** : configurez votre profession, intérêts, niveau d'expertise, objectifs, ton, format, longueur et style d'explication
- **Sélection de modèle** : choisissez parmi tous les modèles disponibles sur OpenRouter

### 🔒 Sécurité & Performance
- **Authentification complète** : inscription, connexion, vérification email, 2FA (passkeys)
- **Rate limiting** : 10 requêtes IA par minute par utilisateur
- **Autorisations** : chaque utilisateur ne peut accéder qu'à ses propres conversations
- **Streaming optimisé** : désactivation des tampons de sortie pour un streaming fluide

## 🛠️ Stack Technique

| Composant | Technologie |
|-----------|-------------|
| **Backend** | Laravel 13, PHP 8.3+ |
| **Frontend** | Vue 3, Inertia.js 3, Tailwind CSS 4 |
| **Base de données** | MySQL (production), SQLite (tests) |
| **IA** | OpenRouter API (OpenAI, Anthropic, etc.) |
| **Tests** | Pest PHP |
| **Auth** | Laravel Fortify (2FA, passkeys) |
| **Build** | Vite 8, TypeScript |

## 📦 Installation

### Prérequis
- PHP 8.3+
- Composer
- Node.js 20+
- MySQL (ou SQLite pour le développement)
- Clé API OpenRouter

### Étapes

```bash
# 1. Cloner le dépôt
git clone https://github.com/charlottebizel/nostradamou.git
cd nostradamou

# 2. Installer les dépendances PHP
composer install

# 3. Configurer l'environnement
cp .env.example .env
php artisan key:generate

# 4. Configurer la base de données dans .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=nostradamou
# DB_USERNAME=root
# DB_PASSWORD=

# 5. Configurer la clé API OpenRouter dans .env
# OPENROUTER_API_KEY=sk-or-v1-votre-cle

# 6. Lancer les migrations
php artisan migrate

# 7. Installer les dépendances frontend
npm install

# 8. Lancer le serveur de développement
composer dev
```

Ou utilisez le script de configuration automatique :

```bash
composer setup
```

## 🚀 Utilisation

1. **Inscrivez-vous** ou **connectez-vous** sur l'application
2. **Créez une conversation** dans l'onglet Chat
3. **Posez votre première question** à l'oracle
4. **Observez** l'oracle devenir de plus en plus ivre à chaque question
5. **Organisez** vos conversations avec des tags
6. **Personnalisez** vos instructions dans les paramètres

### Routes principales

| Méthode | URL | Description |
|---------|-----|-------------|
| `GET` | `/` | Page d'accueil |
| `GET` | `/chat` | Liste des conversations |
| `POST` | `/chat` | Créer une conversation |
| `GET` | `/chat/{conversation}` | Voir une conversation |
| `POST` | `/chat/{conversation}/message` | Envoyer un message (streaming) |
| `POST` | `/chat/{conversation}/tags` | Synchroniser les tags |
| `DELETE` | `/chat/{conversation}` | Supprimer une conversation |
| `GET` | `/ask` | Page de question simple |
| `POST` | `/ask` | Envoyer une question simple |
| `GET` | `/ask-stream` | Page de streaming |
| `POST` | `/ask-stream` | Envoyer une question en streaming |
| `POST` | `/user/settings` | Mettre à jour les paramètres |

## 🧪 Tests

```bash
# Lancer tous les tests
php artisan test

# Lancer les tests d'un fichier spécifique
php artisan test --filter=ConversationControllerTest

# Lancer les tests avec couverture
php artisan test --coverage
```

### Tests inclus
- **ConversationControllerTest** (10 tests) : CRUD conversations, autorisations, tags
- **DashboardTest** : redirections
- **Auth** : authentification, 2FA, passkeys, vérification email
- **Settings** : profil, sécurité

## 📁 Structure du Projet

```
app/
├── Http/
│   └── Controllers/
│       ├── AskController.php          # Questions simples
│       ├── AskStreamController.php    # Streaming SSE
│       ├── ConversationController.php # Gestion des conversations
│       ├── MessageController.php      # Envoi de messages
│       └── UserController.php         # Paramètres utilisateur
├── Models/
│   ├── Conversation.php               # Modèle conversation
│   ├── Message.php                    # Modèle message
│   ├── Tag.php                        # Modèle tag
│   └── User.php                       # Modèle utilisateur
├── Policies/
│   ├── ConversationPolicy.php         # Autorisations conversations
│   ├── MessagePolicy.php              # Autorisations messages
│   └── UserPolicy.php                 # Autorisations utilisateur
├── Providers/
│   └── AppServiceProvider.php         # Rate limiting, config
└── Services/
    ├── SimpleAskService.php           # Service IA (non-streaming)
    └── SimpleAskStreamService.php     # Service IA (streaming)

database/
└── migrations/
    ├── create_conversations_table.php
    ├── create_messages_table.php
    ├── create_tags_table.php
    └── create_conversation_tag_table.php

resources/
├── js/
│   └── pages/
│       ├── Ask/
│       ├── AskStream/
│       └── Chat/
└── views/
    ├── app.blade.php
    └── prompts/
        └── system.blade.php           # Prompt système de l'oracle

tests/
└── Feature/
    ├── ConversationControllerTest.php
    ├── DashboardTest.php
    └── ...
```

## 🔑 Variables d'Environnement

| Variable | Description | Défaut |
|----------|-------------|--------|
| `APP_NAME` | Nom de l'application | Laravel |
| `APP_ENV` | Environnement | local |
| `APP_URL` | URL de l'application | http://nostradamou.test |
| `DB_CONNECTION` | Type de base de données | mysql |
| `DB_HOST` | Hôte de la base | 127.0.0.1 |
| `DB_PORT` | Port de la base | 3306 |
| `DB_DATABASE` | Nom de la base | nostradamou |
| `DB_USERNAME` | Utilisateur de la base | root |
| `DB_PASSWORD` | Mot de passe de la base | (vide) |
| `OPENROUTER_API_KEY` | Clé API OpenRouter | (requis) |
| `OPENROUTER_BASE_URL` | URL de base OpenRouter | https://openrouter.ai/api/v1 |

## 🎭 Le Personnage

L'oracle Nostradamou est un personnage IA unique :

- **Personnalité** : mystique, ivrogne, drôle
- **Comportement** : prend un shot d'alcool à chaque question
- **Progression** : devient de plus en plus ivre (bégaiement, hoquet, visions)
- **Limite** : s'endort après 7 questions
- **Réponses** : mélange de prédictions grandioses et de bêtises de comptoir

Le prompt système est personnalisable dans `resources/views/prompts/system.blade.php`.

## 📝 Licence

MIT License — voir le fichier [LICENSE](LICENSE) pour plus de détails.