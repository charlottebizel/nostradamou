<script setup lang="ts">
import { ref, computed } from 'vue';
import { useStream } from '@laravel/stream-vue';
import MarkdownIt from 'markdown-it';
import hljs from 'highlight.js';
import 'highlight.js/styles/github-dark.css';

const props = defineProps<{
    models: Array<{id: string, name: string}>;
    selectedModel: string;
    selectedModelDetails?: any;
}>();

// Configuration Markdown
const md = new MarkdownIt({
    html: true,
    highlight: function (str, lang) {
        if (lang && hljs.getLanguage(lang)) {
            try {
                return hljs.highlight(str, { language: lang }).value;
            } catch (e) {}
        }
        return '';
    }
});

// State
const message = ref('');
const model = ref(props.selectedModel || 'openai/gpt-4o-mini');
const temperature = ref(1.0);
const reasoningEffort = ref<'low' | 'medium' | 'high' | null>(null);

/**
 * useStream hook - Le hook concatène automatiquement dans `data`
 * Le backend envoie du texte avec marqueurs [REASONING]...[/REASONING]
 */
const { data, isFetching, isStreaming, send, cancel } = useStream(
    '/ask-stream',
    {
        onData: () => {
            // Callback appelé pour chaque chunk reçu
            // Le contenu est automatiquement concaténé dans `data`
        },
        onFinish: () => {
            // Appelé quand le stream se termine
            message.value = '';
        },
        onError: (err: Error) => {
            console.error('Erreur streaming:', err);
        },
    },
);

/**
 * Submit handler - Envoie la requête de streaming
 */
const submit = () => {
    if (!message.value.trim()) return;

    send({
        message: message.value,
        model: model.value,
        temperature: temperature.value,
        reasoning_effort: reasoningEffort.value,
    });
};

/**
 * Extrait le contenu principal (sans le reasoning)
 */
const streamedContent = computed(() => {
    if (!data.value) return '';
    // Enlever les blocs [REASONING]...[/REASONING]
    return data.value
        .replace(/\[REASONING\][\s\S]*?\[\/REASONING\]/g, '')
        .trim();
});

/**
 * Extrait le reasoning des marqueurs
 */
const streamedReasoning = computed(() => {
    if (!data.value) return '';
    const matches = data.value.match(/\[REASONING\]([\s\S]*?)\[\/REASONING\]/g);
    if (!matches) return '';
    return matches
        .map((m) =>
            m.replace(/\[REASONING\]/g, '').replace(/\[\/REASONING\]/g, ''),
        )
        .join('');
});
</script>

<template>
    <div class="max-w-5xl mx-auto p-6 space-y-6 text-white bg-gray-950 min-h-screen">
        <h1 class="text-3xl font-bold text-blue-500">🔮 L'Oracle en Temps Réel (AskStream)</h1>

        <!-- Formulaire de paramètres -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-900 p-4 rounded-lg border border-purple-800/50 shadow-lg">
            <div>
                <label for="model" class="block text-xs uppercase tracking-wider text-purple-400 mb-2 font-bold">Modèle</label>
                <select id="model" v-model="model" class="w-full bg-gray-950 border border-purple-700/50 rounded-lg p-2 text-white text-sm focus:border-blue-500 focus:outline-none">
                    <option v-for="m in models" :key="m.id" :value="m.id">
                        {{ m.name }}
                    </option>
                </select>
            </div>
            <div>
                <label for="temperature" class="block text-xs uppercase tracking-wider text-purple-400 mb-2 font-bold">Température ({{ temperature }})</label>
                <input id="temperature" type="range" min="0" max="2" step="0.1" v-model.number="temperature" class="w-full mt-2 accent-purple-500" />
            </div>
            <div>
                <label for="reasoningEffort" class="block text-xs uppercase tracking-wider text-purple-400 mb-2 font-bold">Effort de Raisonnement</label>
                <select id="reasoningEffort" v-model="reasoningEffort" class="w-full bg-gray-950 border border-purple-700/50 rounded-lg p-2 text-white text-sm focus:border-blue-500 focus:outline-none">
                    <option :value="null">Par défaut</option>
                    <option value="low">Bas</option>
                    <option value="medium">Moyen</option>
                    <option value="high">Élevé</option>
                </select>
            </div>
        </div>

        <!-- Réponse IA -->
        <div v-if="data" class="bg-purple-900/10 border border-purple-800/50 rounded-xl p-6 shadow-lg space-y-6">
            <!-- Affichage du reasoning (optionnel) -->
            <details v-if="streamedReasoning" class="text-sm text-gray-400 bg-purple-900/20 p-4 rounded-xl border border-purple-800/50 shadow-inner group">
                <summary class="cursor-pointer font-bold text-purple-400 hover:text-purple-300 transition-colors uppercase tracking-wider text-xs flex items-center gap-2">
                    <span>🔮 Trace de raisonnement des esprits</span>
                </summary>
                <div class="mt-4 whitespace-pre-wrap font-mono text-xs leading-relaxed text-gray-300 border-t border-purple-800/50 pt-4">
                    {{ streamedReasoning }}
                </div>
            </details>

            <!-- Affichage du contenu principal -->
            <div class="main-content text-gray-200">
                <div class="prose max-w-none dark:prose-invert" v-html="md.render(streamedContent)" />
            </div>
        </div>

        <!-- Input de chat -->
        <form @submit.prevent="submit" class="flex gap-2">
            <input 
                v-model="message" 
                :disabled="isStreaming" 
                placeholder="Pose ta question au destin..." 
                class="flex-1 bg-gray-900 border border-purple-800/50 rounded-lg p-3 text-white focus:outline-none focus:border-blue-500 disabled:opacity-50"
            />
            <button 
                type="submit" 
                :disabled="isStreaming || !message.trim()"
                class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 px-6 rounded-lg transition-colors disabled:opacity-50 flex items-center gap-2"
            >
                <svg v-if="isFetching || isStreaming" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span v-if="isFetching || isStreaming">Connexion en cours...</span>
                <span v-else>🔮 Envoyer</span>
            </button>
            <button 
                v-if="isStreaming || isFetching" 
                type="button" 
                @click="cancel" 
                class="bg-red-900/50 hover:bg-red-800 border border-red-700 text-white font-bold py-3 px-4 rounded-lg transition-colors"
            >
                Arrêter
            </button>
        </form>
    </div>
</template>