<template>
  <div class="flex h-[calc(100vh-200px)] rounded-lg glass-card shadow">
    <div class="flex w-64 flex-col border-r border-gray-200">
      <div class="border-b border-gray-200 p-4">
        <button
          class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
          @click="newConversation"
        >
          Nouvelle conversation
        </button>
      </div>
      <div class="flex-1 overflow-y-auto">
        <div
          v-for="conv in conversations"
          :key="conv.id"
          :class="[
            'cursor-pointer border-b border-gray-100 px-4 py-3 transition hover:glass-bg',
            activeConversation?.id === conv.id ? 'bg-indigo-50' : ''
          ]"
          @click="selectConversation(conv)"
        >
          <p class="truncate text-sm font-medium text-gray-900">{{ conv.title || 'Conversation' }}</p>
          <p class="mt-0.5 truncate text-xs text-gray-400">{{ formatDate(conv.updated_at) }}</p>
        </div>
        <div v-if="conversations.length === 0" class="p-4 text-center text-xs text-gray-400">
          Aucune conversation.
        </div>
      </div>
    </div>

    <div class="flex flex-1 flex-col">
      <div class="border-b border-gray-200 px-6 py-3">
        <h2 class="text-sm font-semibold text-gray-900">
          {{ activeConversation?.title || 'Assistant IA Leopardo' }}
        </h2>
        <p class="text-xs text-gray-500">Consultation des conversations IA des tenants (lecture seule).</p>
      </div>

      <div ref="messagesContainer" class="flex-1 space-y-4 overflow-y-auto p-6">
        <div v-if="messages.length === 0" class="flex h-full items-center justify-center">
          <div class="text-center">
            <ChatBubbleLeftRightIcon class="mx-auto h-12 w-12 text-gray-300" />
            <p class="mt-2 text-sm text-gray-500">Sélectionnez une conversation pour consulter son historique.</p>
          </div>
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
      </div>

      <div class="border-t border-gray-200 p-4">
        <div
          class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
          role="status"
        >
          Le chat assistant n'est pas disponible pour la plateforme super-admin : cette console
          affiche les conversations IA des tenants en lecture seule. Aucune réponse ne peut être
          envoyée depuis ce poste.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { useLocaleStore } from '@/stores/locale'
import { toIntlLocale } from '@/i18n/index.js'

const localeStore = useLocaleStore()
const conversations = ref([])
const activeConversation = ref(null)
const messages = ref([])
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
  try {
    const res = await api.get('/v1/admin/ai/conversations')
    conversations.value = res.data.data || res.data || []
  } catch (err) {
    console.warn('Failed to load AI conversations', err)
  }
}

async function selectConversation(conv) {
  activeConversation.value = conv
  try {
    const res = await api.get(`/v1/admin/ai/conversations/${conv.id}/messages`)
    messages.value = res.data.data || res.data || []
    scrollToBottom()
  } catch (err) {
    console.warn('Failed to load AI conversation messages', err)
  }
}

function newConversation() {
  activeConversation.value = null
  messages.value = []
}

onMounted(fetchConversations)
</script>

