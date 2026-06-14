<script setup>
import { useForm } from '@inertiajs/vue3'
import { ref, nextTick } from 'vue'
import MarkdownIt from 'markdown-it'
import hljs from 'highlight.js'
import 'highlight.js/styles/github-dark.css'

const props = defineProps({
    models: Array,
    selectedModel: String,
    selectedModelDetails: Object,
})

const form = useForm({
    message: '',
    model: props.selectedModel,
    temperature: 1.0,
    reasoning_effort: null,
})

const md = new MarkdownIt({
    html: true,
    highlight: function (str, lang) {
        if (lang && hljs.getLanguage(lang)) {
            try {
                return hljs.highlight(str, { language: lang }).value
            } catch (e) {}
        }
        return ''
    }
})

const parseReasoning = (text) => {
    if (!text) return ''
    let merged = text.replace(/\[\/REASONING\]\s*\[REASONING\]/g, '')
    merged = merged.replace(/\[REASONING\]([\s\S]*?)\[\/REASONING\]/g, '<details class="text-sm text-gray-400 mb-4 bg-purple-900/20 p-4 rounded-xl border border-purple-800/50 shadow-inner"><summary class="cursor-pointer font-bold text-purple-400 hover:text-purple-300 transition-colors uppercase tracking-wider text-xs">🔮 Les esprits murmurent (Réflexion)</summary><div class="mt-3 whitespace-pre-wrap font-mono text-xs leading-relaxed">$1</div></details>')
    return merged
}

const getCookie = (name) => {
    const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
    return (match ? decodeURIComponent(match[3]) : null);
};

const messages = ref([])
const messagesContainer = ref(null)
const processing = ref(false)

const scrollToBottom = async () => {
    await nextTick()
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
}

const sendMessage = async () => {
    if (!form.message.trim()) return

    const userMessage = form.message
    form.message = ''
    
    messages.value.push({
        id: Date.now(),
        role: 'user',
        content: userMessage
    })
    
    const aiMsg = {
        id: Date.now() + 1,
        role: 'assistant',
        content: ''
    }
    messages.value.push(aiMsg)
    
    scrollToBottom()
    processing.value = true

    try {
        const response = await fetch(`/ask-stream`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') || ''
            },
            body: JSON.stringify({
                message: userMessage,
                model: form.model,
                temperature: form.temperature,
                reasoning_effort: form.reasoning_effort,
            })
        })

        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`)
        if (!response.body) throw new Error('ReadableStream not supported')

        const reader = response.body.getReader()
        const decoder = new TextDecoder()

        while (true) {
            const { done, value } = await reader.read()
            if (done) break

            const chunk = decoder.decode(value, { stream: true })
            aiMsg.content += chunk
            scrollToBottom()
        }

        aiMsg.content = parseReasoning(aiMsg.content)
        
    } catch (e) {
        console.error(e)
    } finally {
        processing.value = false
    }
}
</script>

<template>
<div class="h-screen flex flex-col bg-gray-950 text-white">
    <!-- Header -->
    <header class="p-4 border-b border-purple-900 bg-gray-900 flex justify-between items-center">
        <h1 class="text-xl font-bold text-purple-400">🔮 Streaming Pur</h1>
        <div class="flex gap-4">
            <select v-model="form.model" class="bg-gray-800 border border-purple-700 rounded p-2 text-sm text-white focus:outline-none focus:border-blue-500">
                <option v-for="m in models" :key="m.id" :value="m.id">
                    {{ m.name }}
                </option>
            </select>
            <a href="/chat" class="text-blue-400 hover:text-blue-300 text-sm flex items-center">Retour au Chat complet</a>
        </div>
    </header>

    <!-- Chat Area -->
    <main class="flex-1 overflow-hidden flex flex-col max-w-4xl w-full mx-auto">
        <!-- Messages -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-4">
            <div v-if="messages.length === 0" class="text-gray-500 text-center mt-10">
                Pose ta question pour tester le streaming direct (sans sauvegarde en BDD)...
            </div>
            <div v-for="m in messages" :key="m.id" class="mb-4">
                <div v-if="m.role === 'user'" class="text-right">
                    <div class="inline-block bg-gray-800 text-white p-3 rounded-lg border border-gray-600">
                        {{ m.content }}
                    </div>
                </div>
                <div v-else>
                    <div class="prose max-w-none dark:prose-invert bg-purple-900/30 p-4 rounded-lg border border-purple-700" v-html="md.render(m.content)" />
                </div>
            </div>
        </div>

        <!-- Input -->
        <div class="p-4 bg-gray-950 border-t border-purple-900/50">
            <form @submit.prevent="sendMessage" class="flex gap-2">
                <input id="message" name="message" v-model="form.message" :disabled="processing" class="flex-1 border border-purple-800/50 bg-gray-900 rounded p-3 text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none" placeholder="Pose ta question en streaming..." />
                <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded transition-colors font-bold flex items-center gap-2 disabled:opacity-50" :disabled="processing || !form.message.trim()">
                    <span v-if="processing">🔮 Réflexion...</span>
                    <span v-else>Envoyer</span>
                </button>
            </form>
        </div>
    </main>
</div>
</template>
