<template>
  <div class="flex h-[calc(100vh-200px)] rounded-lg bg-white shadow">
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
            'cursor-pointer border-b border-gray-100 px-4 py-3 transition hover:bg-gray-50',
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
        <p class="text-xs text-gray-500">Posez vos questions RH, paie, recrutement...</p>
      </div>

      <div ref="messagesContainer" class="flex-1 space-y-4 overflow-y-auto p-6">
        <div v-if="messages.length === 0" class="flex h-full items-center justify-center">
          <div class="text-center">
            <ChatBubbleLeftRightIcon class="mx-auto h-12 w-12 text-gray-300" />
            <p class="mt-2 text-sm text-gray-500">Commencez une conversation avec l'assistant IA.</p>
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
        <div v-if="streaming" class="flex justify-start">
          <div class="rounded-lg bg-gray-100 px-4 py-3 text-sm text-gray-500">
            <span class="inline-block animate-pulse">Reflexion en cours...</span>
          </div>
        </div>
      </div>

      <div class="border-t border-gray-200 p-4">
        <form class="flex gap-2" @submit.prevent="sendMessage">
          <input
            v-model="inputMessage"
            type="text"
            placeholder="Tapez votre message..."
            class="flex-1 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
            :disabled="streaming"
          />
          <button
            type="submit"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
            :disabled="!inputMessage.trim() || streaming"
          >
            Envoyer
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

const conversations = ref([])
const activeConversation = ref(null)
const messages = ref([])
const inputMessage = ref('')
const streaming = ref(false)
const messagesContainer = ref(null)

function formatDate(date) {
  if (!date) return ''
  return new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })
}

function formatTime(date) {
  if (!date) return ''
  return new Date(date).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
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
    const res = await api.get('/v1/ai/conversations')
    conversations.value = res.data.data || res.data || []
  } catch {}
}

async function selectConversation(conv) {
  activeConversation.value = conv
  try {
    const res = await api.get(`/v1/ai/conversations/${conv.id}/messages`)
    messages.value = res.data.data || res.data || []
    scrollToBottom()
  } catch {}
}

function newConversation() {
  activeConversation.value = null
  messages.value = []
  inputMessage.value = ''
}

async function sendMessage() {
  const text = inputMessage.value.trim()
  if (!text) return

  const userMsg = { id: Date.now(), role: 'user', content: text, created_at: new Date().toISOString() }
  messages.value.push(userMsg)
  inputMessage.value = ''
  scrollToBottom()
  streaming.value = true

  try {
    const payload = {
      message: text,
      conversation_id: activeConversation.value?.id || null,
    }
    const res = await api.post('/v1/ai/chat', payload)
    const reply = res.data
    if (!activeConversation.value && reply.conversation_id) {
      activeConversation.value = { id: reply.conversation_id, title: text.slice(0, 50) }
      fetchConversations()
    }
    messages.value.push({
      id: Date.now() + 1,
      role: 'assistant',
      content: reply.response || reply.message || reply.content || '',
      created_at: new Date().toISOString(),
    })
  } catch {
    messages.value.push({
      id: Date.now() + 1,
      role: 'assistant',
      content: 'Desole, une erreur est survenue. Veuillez reessayer.',
      created_at: new Date().toISOString(),
    })
  } finally {
    streaming.value = false
    scrollToBottom()
  }
}

onMounted(fetchConversations)
</script>
