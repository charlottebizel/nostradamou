<script setup>
import { useForm, Link, router, usePage } from '@inertiajs/vue3'
import { computed, nextTick, ref, watch } from 'vue'
import MarkdownIt from 'markdown-it'
import hljs from 'highlight.js'
import 'highlight.js/styles/github-dark.css'
import DeleteConversation from '@/components/DeleteConversation.vue'

const props = defineProps({
    conversations: Array,
    conversation: Object,
    models: Array,
})

const form = useForm({
    message: ''
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

const page = usePage()

// Gestion de l'affichage (Chat ou Instructions)
const activeTab = ref('chat') // 'chat' ou 'settings'

// Formulaire des instructions personnalisées
const settingsForm = useForm({
    profession: page.props.auth?.user?.settings?.profession || '',
    interests: page.props.auth?.user?.settings?.interests || '',
    expertise_level: page.props.auth?.user?.settings?.expertise_level || '',
    goals: page.props.auth?.user?.settings?.goals || '',
    tone: page.props.auth?.user?.settings?.tone || 'Décontracté',
    format: page.props.auth?.user?.settings?.format || 'Paragraphes',
    length: page.props.auth?.user?.settings?.length || 'Concis',
    explanation_style: page.props.auth?.user?.settings?.explanation_style || 'Analogies'
})

const saveSettings = () => {
    settingsForm.post('/user/settings', {
        onSuccess: () => {
            alert("Instructions personnalisées sauvegardées pour l'oracle !")
            activeTab.value = 'chat'
        }
    })
}

// Tri des conversations par ordre décroissant de fraîcheur
const sortedConversations = computed(() => {
    if (!props.conversations) return []
    return [...props.conversations].sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at))
})

// Modèle de langage : initialisé avec la valeur de la conversation ou de l'utilisateur
const selectedModel = ref(
    props.conversation?.model || page.props.auth?.user?.model || 'openai/gpt-4o-mini'
)

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

watch(() => props.conversation, (newConv) => {
    if (newConv && newConv.model) selectedModel.value = newConv.model
    if (newConv && newConv.messages) {
        newConv.messages.forEach(m => {
            if (m.role === 'assistant' && m.content && m.content.includes('[REASONING]')) {
                m.content = parseReasoning(m.content)
            }
        })
    }
}, { immediate: true })

const updateModel = () => {
    router.put('/user/model', {
        model: selectedModel.value,
        conversation_id: props.conversation?.id
    }, { preserveScroll: true })
}

// scroll auto bas
const messagesContainer = ref(null)

const scrollToBottom = async () => {
    await nextTick()
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop =
            messagesContainer.value.scrollHeight
    }
}

const sendMessage = async () => {
    if (!props.conversation) return

    const userMessage = form.message
    form.message = ''
    
    props.conversation.messages.push({
        id: Date.now(),
        role: 'user',
        content: userMessage
    })
    
    const aiMsg = {
        id: Date.now() + 1,
        role: 'assistant',
        content: ''
    }
    props.conversation.messages.push(aiMsg)
    
    scrollToBottom()
    form.processing = true

    try {
        const response = await fetch(`/chat/${props.conversation.id}/message`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') || ''
            },
            body: JSON.stringify({ message: userMessage })
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

        // Le parsing côté client est géré une seule fois à la fin
        aiMsg.content = parseReasoning(aiMsg.content)
        
        router.reload({ only: ['conversation'] })
    } catch (e) {
        console.error(e)
    } finally {
        form.processing = false
    }
}
</script>

<template>
<div class="grid grid-cols-4 h-screen max-h-screen overflow-hidden bg-gray-950 text-white">

    <!-- SIDEBAR -->
    <aside class="col-span-1 border-r border-purple-900 p-4 overflow-y-auto bg-gray-950">

        <Link
            href="/chat"
            method="post"
            as="button"
            @click="activeTab = 'chat'"
            class="block mb-4 text-blue-600 font-bold w-full text-left transition-colors hover:text-blue-500"
        >
            + Nouvelle conversation
        </Link>

        <!-- ONGLET INSTRUCTIONS PERSONNALISEES -->
        <button
            @click="activeTab = 'settings'"
            class="block mb-6 text-gray-300 font-bold w-full text-left transition-colors hover:text-white flex items-center gap-2"
            :class="{ 'text-purple-400': activeTab === 'settings' }"
        >
            <span class="text-lg">⚙️</span> Instructions personnalisées
        </button>

        <!-- SELECTEUR DE MODELE -->
        <div class="mb-6">
            <label for="model" class="block text-xs uppercase tracking-wider text-purple-400 mb-2 font-bold">Modèle d'IA</label>
            <select
                id="model"
                name="model"
                v-model="selectedModel"
                @change="updateModel"
                class="w-full bg-gray-900 border border-purple-700 rounded p-2 text-white text-sm focus:outline-none focus:border-blue-500"
            >
                <option v-for="m in models" :key="m.id" :value="m.id">
                    {{ m.name }}
                </option>
            </select>
        </div>

        <div class="space-y-2">
            <div v-for="c in sortedConversations" :key="c.id" class="relative group">
    
                <Link
                    :href="`/chat/${c.id}`"
                    @click="activeTab = 'chat'"
                    class="block p-4 pr-10 rounded-lg border border-purple-700 bg-purple-900/30 transition-colors"
                    :class="conversation?.id === c.id ? 'bg-purple-800' : 'hover:bg-purple-800/50'"
                >
                    {{ c.title ?? 'Nouvelle conversation' }}
                </Link>
    
                <!-- BOUTON DE SUPPRESSION -->
                <DeleteConversation :conversation="c" />
            </div>
        </div>

    </aside>

    <!-- CHAT -->
    <main class="col-span-3 flex flex-col min-h-0 h-full bg-gradient-to-b from-gray-950 via-purple-950 to-black">

        <!-- SECTION : INSTRUCTIONS PERSONNALISEES -->
        <div v-if="activeTab === 'settings'" class="flex-1 overflow-y-auto p-8 space-y-8 text-gray-200 min-h-0">
            <div class="flex items-center justify-between mb-4 border-b border-purple-900 pb-4">
                <h2 class="text-2xl font-bold text-white flex items-center gap-2">⚙️ Instructions Personnalisées</h2>
                <button @click="activeTab = 'chat'" class="text-gray-400 hover:text-white text-sm font-bold uppercase tracking-wider">✕ Fermer</button>
            </div>

            <form @submit.prevent="saveSettings" class="space-y-8 max-w-4xl pb-10">
                
                <!-- A PROPOS DE NOUS -->
                <section class="bg-purple-900/10 p-6 rounded-xl border border-purple-800/50 shadow-lg">
                    <h3 class="text-lg font-bold text-purple-400 mb-4 flex items-center gap-2">👤 À propos de vous</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="profession" class="block text-sm font-bold text-gray-300 mb-1">Ta profession</label>
                            <input id="profession" name="profession" v-model="settingsForm.profession" type="text" class="w-full bg-gray-900 border border-purple-700/50 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 focus:outline-none" placeholder="Ex: Développeur, Étudiant..." />
                            <p class="text-xs text-gray-500 mt-1">Réponses adaptées à ton niveau.</p>
                        </div>
                        <div>
                            <label for="interests" class="block text-sm font-bold text-gray-300 mb-1">Tes centres d'intérêt</label>
                            <input id="interests" name="interests" v-model="settingsForm.interests" type="text" class="w-full bg-gray-900 border border-purple-700/50 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 focus:outline-none" placeholder="Ex: Science-fiction, Cuisine..." />
                            <p class="text-xs text-gray-500 mt-1">Exemples et analogies pertinents.</p>
                        </div>
                        <div>
                            <label for="expertise_level" class="block text-sm font-bold text-gray-300 mb-1">Ton niveau d'expertise</label>
                            <select id="expertise_level" name="expertise_level" v-model="settingsForm.expertise_level" class="w-full bg-gray-900 border border-purple-700/50 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 focus:outline-none">
                                <option value="" disabled>Sélectionne un niveau</option>
                                <option>Débutant</option>
                                <option>Intermédiaire</option>
                                <option>Avancé</option>
                                <option>Expert</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Explications ni trop simples ni trop complexes.</p>
                        </div>
                        <div>
                            <label for="goals" class="block text-sm font-bold text-gray-300 mb-1">Tes objectifs</label>
                            <input id="goals" name="goals" v-model="settingsForm.goals" type="text" class="w-full bg-gray-900 border border-purple-700/50 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 focus:outline-none" placeholder="Ex: Gagner en productivité..." />
                            <p class="text-xs text-gray-500 mt-1">Aide orientée vers tes vrais besoins.</p>
                        </div>
                    </div>
                </section>

                <!-- COMPORTEMENT DE L'ASSISTANT -->
                <section class="bg-purple-900/10 p-6 rounded-xl border border-purple-800/50 shadow-lg">
                    <h3 class="text-lg font-bold text-purple-400 mb-4 flex items-center gap-2">🤖 Comportement de l'assistant</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="tone" class="block text-sm font-bold text-gray-300 mb-1">Ton</label>
                            <select id="tone" name="tone" v-model="settingsForm.tone" class="w-full bg-gray-900 border border-purple-700/50 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 focus:outline-none">
                                <option>Formel</option>
                                <option>Décontracté</option>
                                <option>Technique</option>
                                <option>Pédagogique</option>
                            </select>
                        </div>
                        <div>
                            <label for="format" class="block text-sm font-bold text-gray-300 mb-1">Format</label>
                            <select id="format" name="format" v-model="settingsForm.format" class="w-full bg-gray-900 border border-purple-700/50 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 focus:outline-none">
                                <option>Listes</option>
                                <option>Paragraphes</option>
                                <option>Tableaux</option>
                                <option>Code first</option>
                            </select>
                        </div>
                        <div>
                            <label for="length" class="block text-sm font-bold text-gray-300 mb-1">Longueur</label>
                            <select id="length" name="length" v-model="settingsForm.length" class="w-full bg-gray-900 border border-purple-700/50 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 focus:outline-none">
                                <option>Concis</option>
                                <option>Détaillé</option>
                                <option>Va droit au but</option>
                            </select>
                        </div>
                        <div>
                            <label for="explanation_style" class="block text-sm font-bold text-gray-300 mb-1">Style d'explication</label>
                            <select id="explanation_style" name="explanation_style" v-model="settingsForm.explanation_style" class="w-full bg-gray-900 border border-purple-700/50 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 focus:outline-none">
                                <option>Analogies</option>
                                <option>Exemples pratiques</option>
                                <option>Théorie</option>
                            </select>
                        </div>
                    </div>
                </section>

                <!-- COMMANDES PERSONNALISEES -->
                <section class="bg-purple-900/10 p-6 rounded-xl border border-purple-800/50 shadow-lg">
                    <h3 class="text-lg font-bold text-purple-400 mb-2 flex items-center gap-2">⚡ Commandes personnalisées</h3>
                    <p class="text-sm text-gray-400 mb-4">Utilise ces commandes rapides dans le chat pour déclencher des actions spécifiques.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div class="bg-gray-950/50 border border-gray-800 p-4 rounded-lg flex flex-col gap-1 transition-colors hover:border-blue-500">
                            <span class="font-bold text-blue-400 font-mono">/test</span>
                            <span class="text-gray-400 leading-snug">Teste le code.</span>
                        </div>
                        <div class="bg-gray-950/50 border border-gray-800 p-4 rounded-lg flex flex-col gap-1 transition-colors hover:border-blue-500">
                            <span class="font-bold text-blue-400 font-mono">/citation</span>
                            <span class="text-gray-400 leading-snug">Génère une citation inspirante (ou dans un domaine précis).</span>
                        </div>
                        <div class="bg-gray-950/50 border border-gray-800 p-4 rounded-lg flex flex-col gap-1 transition-colors hover:border-blue-500">
                            <span class="font-bold text-blue-400 font-mono">/résumé</span>
                            <span class="text-gray-400 leading-snug">Résume le texte ou l'article que tu colles ensuite.</span>
                        </div>
                        <div class="bg-gray-950/50 border border-gray-800 p-4 rounded-lg flex flex-col gap-1 transition-colors hover:border-blue-500">
                            <span class="font-bold text-blue-400 font-mono">/review</span>
                            <span class="text-gray-400 leading-snug">Analyse et critique ton code source.</span>
                        </div>
                        <div class="bg-gray-950/50 border border-gray-800 p-4 rounded-lg flex flex-col gap-1 transition-colors hover:border-blue-500">
                            <span class="font-bold text-blue-400 font-mono">/explain</span>
                            <span class="text-gray-400 leading-snug">Explique le concept comme si j'avais 5 ans.</span>
                        </div>
                        <div class="bg-gray-950/50 border border-gray-800 p-4 rounded-lg flex flex-col gap-1 transition-colors hover:border-blue-500">
                            <span class="font-bold text-blue-400 font-mono">/feedback</span>
                            <span class="text-gray-400 leading-snug">Formule pour envoyer un retour constructif.</span>
                        </div>
                    </div>
                </section>

                <div class="flex justify-end pt-4">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-8 py-3 rounded-lg transition-colors font-bold shadow-lg shadow-blue-900/20">
                        Sauvegarder les instructions
                    </button>
                </div>
            </form>
        </div>

        <!-- SECTION : CHAT (AFFICHAGE CONDITIONNEL) -->
        <template v-else>
            <!-- MESSAGES -->
            <div ref="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-4 min-h-0">
                <div v-if="conversation">
                    <div v-for="m in conversation.messages" :key="m.id" class="mb-4">
                        <!-- USER -->
                        <div v-if="m.role === 'user'" class="text-right">
                            <div class="inline-block bg-gray-800 text-white p-3 rounded-lg border border-gray-600">
                                {{ m.content }}
                            </div>
                        </div>
                        <!-- AI -->
                        <div v-else>
                            <div class="prose max-w-none dark:prose-invert bg-purple-900/30 p-4 rounded-lg border border-purple-700" v-html="md.render(m.content)" />
                        </div>
                    </div>
                </div>
                <div v-else class="text-gray-500">
                    Sélectionne une conversation
                </div>
            </div>

            <!-- INPUT -->
            <div class="border-t p-4 border-purple-900/50 bg-gray-950">
                <form @submit.prevent="sendMessage" class="flex gap-2">
                    <input id="message" name="message" :disabled="!conversation" v-model="form.message" class="flex-1 border border-purple-800/50 bg-gray-900 rounded p-2 text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none" placeholder="Pose ta question au destin..." />
                    <button class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded transition-colors flex items-center gap-2 disabled:opacity-50" :disabled="form.processing || !conversation">
                        <svg v-if="form.processing" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span v-if="form.processing">🔮 ... connexion au futur...</span>
                        <span v-else>🔮 demander au destin 🔮</span>
                    </button>
                </form>
            </div>
        </template>

    </main>

</div>
</template>