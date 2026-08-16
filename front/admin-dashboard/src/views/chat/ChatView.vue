<template>
  <div class="flex h-[calc(100vh-200px)] rounded-lg glass-card shadow">
    <div class="flex w-64 flex-col border-r border-gray-200">
      <div class="border-b border-gray-200 p-4">
        <button
          class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
          @click="newConversation"
        >
          {{ t('adminChat.new', 'Nouvelle conversation') }}
        </button>
      </div>
      <div class="flex-1 overflow-y-auto">
        <div
          v-for="conv in conversations"
          :key="conv.id"
          :class="[
            'cursor-pointer border-b border-gray-100 px-4 py-3 transition glass-bg-hover',
            activeConversation?.id === conv.id ? 'bg-indigo-50' : ''
          ]"
          @click="selectConversation(conv)"
        >
          <p class="truncate text-sm font-medium text-gray-900">{{ conv.title || t('adminChat.conversation', 'Conversation') }}</p>
          <p class="mt-0.5 truncate text-xs text-gray-400">{{ formatDate(conv.updated_at) }}</p>
        </div>
        <div v-if="conversationsError" class="p-4 text-center" role="alert">
          <p class="text-xs text-red-500">{{ t('adminChat.historyError', 'Impossible de charger les conversations.') }}</p>
          <button
            class="mt-2 rounded-md bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-700"
            @click="fetchConversations"
          >
            {{ t('adminChat.retry', 'Réessayer') }}
          </button>
        </div>
        <div v-else-if="conversations.length === 0" class="p-4 text-center text-xs text-gray-400">
          {{ t('adminChat.historyEmpty', 'Aucune conversation.') }}
        </div>
      </div>
    </div>

    <div class="flex flex-1 flex-col">
      <div class="border-b border-gray-200 px-6 py-3">
        <h2 class="text-sm font-semibold text-gray-900">
          {{ activeConversation?.title || t('adminChat.title', 'Assistant IA Leopardo') }}
        </h2>
        <p class="text-xs text-gray-500">{{ t('adminChat.subtitle', 'Posez vos questions RH, paie, recrutement...') }}</p>
      </div>

      <div ref="messagesContainer" class="flex-1 space-y-4 overflow-y-auto p-6">
        <div class="mx-auto mb-6 max-w-2xl rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-900" role="status">
          <p class="font-semibold">{{ t('adminChat.unavailableTitle', 'Assistant IA indisponible au niveau plateforme') }}</p>
          <p class="mt-1 text-sm">{{ t('adminChat.unavailableBody', 'Le chat IA n’est pas activé pour la console super-admin.') }}</p>
        </div>
        <div v-if="messages.length === 0" class="flex h-full items-center justify-center">
          <div class="text-center">
            <ChatBubbleLeftRightIcon class="mx-auto h-12 w-12 text-gray-300" />
            <p class="mt-2 text-sm text-gray-500">{{ t('adminChat.start', "Commencez une conversation avec l'assistant IA.") }}</p>
          </div>
        </div>
        <div v-if="messagesError" class="mx-auto mt-4 max-w-2xl rounded-lg border border-red-200 bg-red-50 p-4 text-center text-sm text-red-700" role="alert">
          <p>{{ t('adminChat.messagesError', 'Impossible de charger les messages.') }}</p>
          <button
            class="mt-2 rounded-md bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-700"
            @click="retryMessages"
          >
            {{ t('adminChat.retry', 'Réessayer') }}
          </button>
        </div>
        <div
          v-for="msg in messages"
          :key="msg.id"
          :class="['flex', msg.role === 'user' ? 'justify-end' : 'justify-start']"
        >
          <div
            :class="[
              'max-w-[70%] rounded-lg px-4 py-3 text-sm',
              msg.role === 'user'
                ? 'bg-indigo-600 text-white'
                : 'bg-gray-100 text-gray-900'
            ]"
          >
            <p class="whitespace-pre-wrap">{{ msg.content }}</p>
            <p :class="['mt-1 text-xs', msg.role === 'user' ? 'text-indigo-200' : 'text-gray-400']">
              {{ formatTime(msg.created_at) }}
            </p>
          </div>
        </div>
        <div v-if="streaming" class="flex justify-start">
          <div class="rounded-lg bg-gray-100 px-4 py-3 text-sm text-gray-500">
            <span class="inline-block animate-pulse">{{ t('adminChat.thinking', 'Réflexion en cours...') }}</span>
          </div>
        </div>
      </div>

      <div class="border-t border-gray-200 p-4">
        <div
          v-if="chatUnavailable"
          class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700"
        >
          Chat IA plateforme indisponible (contrat 501 ADMIN_CHAT_UNAVAILABLE). Le service reviendra quand le backend sera branché.
        </div>
        <form class="flex gap-2" @submit.prevent="sendMessage">
          <input
            v-model="inputMessage"
            type="text"
:placeholder="t('adminChat.placeholder', 'Tapez votre message...')"
            class="flex-1 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
:disabled="true"
          />
          <button
            type="submit"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
:disabled="true"
          >
            {{ t('adminChat.send', 'Envoyer') }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { useLocaleStore } from '@/stores/locale'
import { toIntlLocale, translate } from '@/i18n/index.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
// #4564 : refs d'état d'erreur manquants (utilisés par le template + fetch, #4333).
const conversationsError = ref(false)
const messagesError = ref(false)
const conversations = ref([])
const activeConversation = ref(null)
const messages = ref([])
const inputMessage = ref('')
const streaming = ref(false)
// Contrat backend : POST /admin/ai/chat retourne 501 ADMIN_CHAT_UNAVAILABLE
// tant que le service n'est pas branché (#3390) — composer désactivé et état
// « indisponible » affiché honnêtement (fix #3809 : la ref restait false et
// l'état honnête n'apparaissait jamais).
const chatUnavailable = ref(true)
const messagesContainer = ref(null)

function formatDate(date) {
  if (!date) return ''
  return new Date(date).toLocaleDateString(toIntlLocale(localeStore.current), { day: '2-digit', month: '2-digit' })
}

function formatTime(date) {
  if (!date) return ''
  return new Date(date).toLocaleTimeString(toIntlLocale(localeStore.current), { hour: '2-digit', minute: '2-digit' })
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

async function fetchConversations() {
  conversationsError.value = false
  try {
    const res = await api.get('/admin/ai/conversations')
    conversations.value = res.data.data || res.data || []
  } catch (err) {
    // #4333 : état d'erreur visible + retry (l'intercepteur global toast déjà).
    conversationsError.value = true
    console.warn('Failed to load AI conversations', err)
  }
}

async function selectConversation(conv) {
  activeConversation.value = conv
  messagesError.value = false
  try {
    const res = await api.get(`/admin/ai/conversations/${conv.id}/messages`)
    messages.value = res.data.data || res.data || []
    scrollToBottom()
  } catch (err) {
    // #4333 : garder les messages déjà chargés + état d'erreur avec retry.
    messagesError.value = true
    console.warn('Failed to load AI conversation messages', err)
  }
}

function retryMessages() {
  if (activeConversation.value) selectConversation(activeConversation.value)
}

function newConversation() {
  activeConversation.value = null
  messages.value = []
  inputMessage.value = ''
}

function sendMessage() {
  // Intentionally disabled: the platform API returns 501 for cross-tenant AI chat.
}


// Conversation history stays readable, but the platform-level composer is disabled
// because the backend intentionally returns 501 for cross-tenant AI chat.
onMounted(fetchConversations)
</script>

