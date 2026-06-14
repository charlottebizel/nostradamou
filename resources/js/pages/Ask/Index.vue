<script setup>
import { useForm } from '@inertiajs/vue3'
import MarkdownIt from 'markdown-it'
import hljs from 'highlight.js'
import 'highlight.js/styles/github-dark.css'

// 📦 props venant de Laravel
const props = defineProps({
    models: Array,
    selectedModel: String,
    message: String,
    response: String,
    error: String,
})

// 🧠 form Inertia
const form = useForm({
    message: props.message ?? '',
    model: props.selectedModel,
})

// 🚀 markdown renderer
const md = new MarkdownIt({
    highlight: (str, lang) => {
        if (lang && hljs.getLanguage(lang)) {
            return hljs.highlight(str, { language: lang }).value
        }
        return ''
    }
})

// 📤 submit vers Laravel
const submit = () => {
    form.post('/ask')
}
</script>

<template>
    <div class="max-w-3xl mx-auto p-6">

        <!-- Titre -->
        <h1 class="text-2xl font-bold mb-4">
            Mini ChatGPT
        </h1>

        <!-- ERREUR -->
        <div v-if="error" class="bg-red-100 text-red-600 p-3 mb-3 rounded">
            {{ error }}
        </div>

        <!-- FORMULAIRE -->
        <form @submit.prevent="submit" class="space-y-4">

            <!-- MESSAGE -->
            <textarea
                v-model="form.message"
                class="w-full border p-3 rounded"
                placeholder="Pose ta question..."
            ></textarea>

            <!-- MODELE -->
            <select v-model="form.model" class="w-full border p-3 rounded">
                <option
                    v-for="m in models"
                    :key="m.id"
                    :value="m.id"
                >
                    {{ m.name }}
                </option>
            </select>

            <!-- BOUTON -->
            <button
                type="submit"
                class="bg-black text-white px-4 py-2 rounded"
                :disabled="form.processing"
            >
                Envoyer
            </button>
        </form>

        <!-- REPONSE -->
        <div
            v-if="response"
            class="prose dark:prose-invert mt-6 max-w-none"
            v-html="md.render(response)"
        ></div>

    </div>
</template>