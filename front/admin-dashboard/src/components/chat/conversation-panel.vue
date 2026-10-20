<template>
  <section class="flex h-full min-h-0 flex-1 flex-col">
    <!-- En-tête -->
    <header class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3 dark:border-slate-700">
      <div class="min-w-0">
        <h2 class="truncate text-sm font-bold text-slate-900 dark:text-white">
          {{ headerTitle }}
        </h2>
        <p class="truncate text-xs text-slate-500 dark:text-slate-400">
          {{ headerSubtitle }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <span
          v-if="offline"
          class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
          role="status"
        >
          {{ t('chatHub.thread.offline') }}
        </span>
        <span
          v-else-if="!loading"
          class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
          role="status"
        >
          {{ t('chatHub.thread.live') }}
        </span>
      </div>
    </header>

    <!-- Chargement initial -->
    <div v-if="loading" class="flex flex-1 items-center justify-center gap-3 text-slate-400">
      <div class="h-5 w-5 animate-spin rounded-full border-2 border-indigo-500 border-t-transparent" />
      <span class="text-sm">{{ t('chatHub.common.loading') }}</span>
    </div>

    <!-- Erreur de chargement -->
    <div v-else-if="error" class="flex flex-1 flex-col items-center justify-center gap-3 px-6 text-center" role="alert">
      <ExclamationTriangleIcon class="h-10 w-10 text-red-400" />
      <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ t('chatHub.thread.loadError') }}</p>
      <button
        type="button"
        class="rounded-full bg-indigo-600 px-5 py-2 text-xs font-bold text-white hover:bg-indigo-700"
        @click="$emit('retry')"
      >
        {{ t('chatHub.common.retry') }}
      </button>
    </div>

    <template v-else>
      <!-- Messages -->
      <div ref="scroller" class="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4">
        <div class="flex justify-center">
          <button
            v-if="hasMore"
            type="button"
            :disabled="loadingOlder"
            class="rounded-full border border-slate-200 px-4 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:text-slate-300"
            @click="$emit('load-older')"
          >
            {{ loadingOlder ? t('chatHub.common.loading') : t('chatHub.thread.loadOlder') }}
          </button>
        </div>

        <div v-if="messages.length === 0" class="flex h-full flex-col items-center justify-center gap-2 text-center text-slate-400">
          <ChatBubbleLeftRightIcon class="h-10 w-10" />
          <p class="text-sm font-medium">{{ t('chatHub.thread.empty') }}</p>
          <p class="text-xs">{{ t('chatHub.thread.emptyHint') }}</p>
        </div>

        <article
          v-for="message in messages"
          :key="message.id"
          :class="['flex', isMine(message) ? 'justify-end' : 'justify-start']"
        >
          <div
            :class="[
              'max-w-[85%] rounded-2xl px-4 py-2.5 text-sm sm:max-w-[70%]',
              isMine(message)
                ? 'bg-indigo-600 text-white'
                : 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-slate-100'
            ]"
          >
            <p
              v-if="!isMine(message) && message.author?.name"
              :class="['mb-1 text-xs font-bold', 'text-slate-500 dark:text-slate-400']"
            >
              {{ message.author.name }}
            </p>

            <!-- Réponse citée -->
            <div
              v-if="message.reply_to"
              :class="[
                'mb-2 rounded-lg border-s-2 px-2 py-1 text-xs',
                isMine(message)
                  ? 'border-indigo-300 bg-indigo-500/40 text-indigo-50'
                  : 'border-slate-300 bg-white/70 text-slate-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-400'
              ]"
            >
              <span class="italic">
                {{ message.reply_to.deleted ? t('chatHub.thread.deletedMessage') : truncate(message.reply_to.body, 90) }}
              </span>
            </div>

            <!-- Corps / supprimé -->
            <p v-if="message.deleted" class="italic opacity-70">{{ t('chatHub.thread.deletedMessage') }}</p>
            <p v-else class="whitespace-pre-wrap break-words">{{ message.body }}</p>

            <!-- Pièce jointe -->
            <a
              v-if="message.attachment"
              :href="attachmentHref(message)"
              class="mt-2 flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs underline underline-offset-2"
              :class="isMine(message) ? 'bg-indigo-500/50 text-indigo-50' : 'bg-white/70 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300'"
              download
              @click.prevent="$emit('download', message)"
            >
              <PaperClipIcon class="h-4 w-4 shrink-0" />
              <span class="truncate">{{ message.attachment.original_name || t('chatHub.thread.attachment') }}</span>
              <span class="shrink-0 opacity-70">{{ formatSize(message.attachment.size) }}</span>
            </a>

            <div
              :class="[
                'mt-1 flex items-center gap-2 text-[11px]',
                isMine(message) ? 'text-indigo-200' : 'text-slate-400'
              ]"
            >
              <span>{{ formatTime(message.created_at) }}</span>
              <span v-if="message.edited_at">{{ t('chatHub.thread.edited') }}</span>
            </div>

            <!-- Actions -->
            <div v-if="!message.deleted" class="mt-1 flex flex-wrap gap-2">
              <button
                type="button"
                :class="['text-[11px] underline underline-offset-2', isMine(message) ? 'text-indigo-100' : 'text-slate-500 hover:text-slate-700']"
                @click="$emit('reply', message)"
              >
                {{ t('chatHub.thread.reply') }}
              </button>
              <button
                v-if="isMine(message)"
                type="button"
                :class="['text-[11px] underline underline-offset-2', 'text-indigo-100 hover:text-white']"
                @click="startEdit(message)"
              >
                {{ t('chatHub.thread.edit') }}
              </button>
              <button
                v-if="isMine(message) || isGroupAdmin"
                type="button"
                :class="['text-[11px] underline underline-offset-2', 'text-indigo-100 hover:text-white']"
                @click="$emit('delete', message)"
              >
                {{ t('chatHub.thread.delete') }}
              </button>
              <button
                v-if="!isMine(message)"
                :class="['text-[11px] underline underline-offset-2', 'text-slate-500 hover:text-red-600']"
                type="button"
                @click="$emit('report', message)"
              >
                {{ t('chatHub.thread.report') }}
              </button>
            </div>
          </div>
        </article>
      </div>

      <!-- Édition en cours -->
      <div v-if="editing" class="border-t border-amber-200 bg-amber-50 px-4 py-2 dark:border-amber-800 dark:bg-amber-900/20">
        <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">{{ t('chatHub.thread.editingMessage') }}</p>
        <div class="mt-2 flex gap-2">
          <input
            v-model="editBody"
            type="text"
            class="flex-1 rounded-lg border-amber-300 text-sm dark:bg-slate-800 dark:text-white"
            @keyup.enter="saveEdit"
          />
          <button type="button" class="rounded-full bg-amber-600 px-4 py-1 text-xs font-bold text-white" @click="saveEdit">
            {{ t('chatHub.common.save') }}
          </button>
          <button type="button" class="rounded-full border border-amber-300 px-4 py-1 text-xs font-semibold text-amber-800" @click="cancelEdit">
            {{ t('chatHub.common.cancel') }}
          </button>
        </div>
      </div>

      <!-- Réponse en cours -->
      <div v-if="replyTarget" class="border-t border-slate-200 bg-slate-50 px-4 py-2 dark:border-slate-700 dark:bg-slate-800/60">
        <div class="flex items-center justify-between gap-2">
          <p class="truncate text-xs text-slate-500 dark:text-slate-400">
            <span class="font-semibold">{{ t('chatHub.thread.replyingTo') }}</span>
            {{ truncate(replyTarget.body, 80) }}
          </p>
          <button type="button" class="text-xs text-slate-400 hover:text-red-500" @click="cancelReply">
            ✕
          </button>
        </div>
      </div>

      <!-- Composeur -->
      <div class="border-t border-slate-200 p-3 dark:border-slate-700">
        <p v-if="sendError" class="mb-2 rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700 dark:bg-red-900/20 dark:text-red-300" role="alert">
          {{ sendError }}
        </p>

        <div v-if="pendingAttachment" class="mb-2 flex items-center justify-between gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-xs dark:bg-slate-800">
          <span class="flex min-w-0 items-center gap-2">
            <PaperClipIcon class="h-4 w-4 shrink-0 text-slate-500" />
            <span class="truncate">{{ pendingAttachment.name }}</span>
            <span class="shrink-0 text-slate-400">{{ formatSize(pendingAttachment.size) }}</span>
          </span>
          <button type="button" class="shrink-0 text-slate-400 hover:text-red-500" :aria-label="t('chatHub.common.cancel')" @click="pendingAttachment = null">
            ✕
          </button>
        </div>

        <form class="flex items-end gap-2" @submit.prevent="submit">
          <label class="sr-only" :for="`chat-file-${thread?.id ?? 'new'}`">{{ t('chatHub.thread.attach') }}</label>
          <input
            :id="`chat-file-${thread?.id ?? 'new'}`"
            ref="fileInput"
            type="file"
            class="hidden"
            @change="onFilePicked"
          />
          <button
            type="button"
            class="rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300"
            :aria-label="t('chatHub.thread.attach')"
            @click="fileInput?.click()"
          >
            <PaperClipIcon class="h-5 w-5" />
          </button>

          <label class="sr-only" :for="`chat-input-${thread?.id ?? 'new'}`">{{ t('chatHub.thread.placeholder') }}</label>
          <textarea
            :id="`chat-input-${thread?.id ?? 'new'}`"
            v-model="draft"
            rows="1"
            :placeholder="t('chatHub.thread.placeholder')"
            class="max-h-32 min-h-[2.5rem] flex-1 resize-y rounded-lg border-slate-200 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            :disabled="sending"
            @keydown.enter.exact.prevent="submit"
          />

          <button
            type="submit"
            class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-50"
            :disabled="sending || (!draft.trim() && !pendingAttachment)"
          >
            {{ sending ? t('chatHub.thread.sending') : t('chatHub.thread.send') }}
          </button>
        </form>

        <p class="mt-1 text-[11px] text-slate-400">{{ t('chatHub.thread.enterHint') }}</p>
      </div>
    </template>
  </section>
</template>

<script setup>
/**
 * #8754 [BOS-CHAT] — écran de conversation (chat horizontal, BC-13 COMMS).
 *
 * Composant purement présentationnel : aucun appel réseau, aucune règle
 * d'autorisation. Le parent (`ChatHubView.vue`) possède l'état et appelle le
 * serveur ; ici on affiche, on compose et on émet des intentions.
 *
 * ⚠️ Chat HUMAIN uniquement. L'assistant IA (Léa) est un autre produit : voir
 * `@/views/chat/ChatView.vue`. Aucune fusion d'historique, et une instruction
 * écrite ici ne déclenche jamais d'action métier.
 */
import { computed, nextTick, ref, watch } from 'vue'
import {
  ChatBubbleLeftRightIcon,
  ExclamationTriangleIcon,
  PaperClipIcon,
} from '@heroicons/vue/24/outline'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { attachmentUrl, newClientMessageId } from '@/lib/chat-api'

const props = defineProps({
  thread: { type: Object, default: null },
  messages: { type: Array, default: () => [] },
  currentEmployeeId: { type: Number, default: null },
  loading: { type: Boolean, default: false },
  loadingOlder: { type: Boolean, default: false },
  hasMore: { type: Boolean, default: false },
  error: { type: Boolean, default: false },
  offline: { type: Boolean, default: false },
  sending: { type: Boolean, default: false },
  sendError: { type: String, default: '' },
  /** Taille maximale acceptée par le serveur (`conversations.attachments.max_kb`). */
  maxAttachmentKb: { type: Number, default: 5120 },
})

const emit = defineEmits([
  'send',
  'load-older',
  'retry',
  'reply',
  'delete',
  'report',
  'download',
  'edit',
])

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const scroller = ref(null)
const fileInput = ref(null)
const draft = ref('')
const pendingAttachment = ref(null)
const replyTarget = ref(null)
const editing = ref(null)
const editBody = ref('')

const headerTitle = computed(() => {
  if (!props.thread) return t('chatHub.thread.selectTitle')

  if (props.thread.title && String(props.thread.title).trim() !== '') return props.thread.title
  if (props.thread.kind === 'group') return t('chatHub.list.kindGroup')

  return props.thread.manager?.name || props.thread.employee?.name || t('chatHub.list.conversation')
})

const headerSubtitle = computed(() => {
  if (!props.thread) return t('chatHub.thread.selectHint')

  if (props.thread.kind === 'group') {
    const count = Array.isArray(props.thread.participants) ? props.thread.participants.length : 0

    return `${t('chatHub.thread.groupMembers')} · ${count}`
  }

  return props.thread.subject_type
    ? t(`chatHub.subjects.${props.thread.subject_type}`, props.thread.subject_type)
    : t('chatHub.thread.directSubtitle')
})

/** Un administrateur de groupe peut supprimer le message d'un autre. */
const isGroupAdmin = computed(() => {
  if (!props.thread || props.thread.kind !== 'group') return false
  if (!Array.isArray(props.thread.participants) || props.currentEmployeeId == null) return false

  const me = props.thread.participants.find((participant) => participant.employee_id === props.currentEmployeeId)

  return me?.role === 'admin' || me?.role === 'creator'
})

function isMine(message) {
  return props.currentEmployeeId != null && message.author_id === props.currentEmployeeId
}

function attachmentHref(message) {
  return props.thread ? attachmentUrl(props.thread.id, message.id) : '#'
}

function truncate(value, max) {
  const text = String(value ?? '')

  return text.length > max ? `${text.slice(0, max)}…` : text
}

function formatTime(value) {
  if (!value) return ''

  try {
    return new Date(value).toLocaleTimeString(localeStore.current, { hour: '2-digit', minute: '2-digit' })
  } catch {
    return ''
  }
}

function formatSize(bytes) {
  if (!bytes || Number.isNaN(Number(bytes))) return ''

  const size = Number(bytes)

  if (size < 1024) return `${size} o`
  if (size < 1024 * 1024) return `${Math.round(size / 1024)} Ko`

  return `${(size / (1024 * 1024)).toFixed(1)} Mo`
}

function scrollToBottom() {
  nextTick(() => {
    if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight
  })
}

/** Au changement de fil : on vide le brouillon et on va en bas. */
watch(
  () => props.thread?.id,
  () => {
    draft.value = ''
    pendingAttachment.value = null
    replyTarget.value = null
    editing.value = null
    scrollToBottom()
  },
)

/** Nouveau message : on suit le fil si l'utilisateur est déjà en bas. */
watch(
  () => props.messages.length,
  (next, previous) => {
    if (next > previous) scrollToBottom()
  },
  { immediate: true },
)

function onFilePicked(event) {
  const file = event.target?.files?.[0]

  if (!file) return

  // Contrôle CÔTÉ CLIENT pour l'ergonomie seulement : le serveur revalide la
  // taille ET le type réel (`finfo`) — un `.pdf` contenant un exécutable est
  // refusé côté serveur même si ce contrôle passe.
  if (file.size > props.maxAttachmentKb * 1024) {
    emit('send', { error: 'too-large' })
    event.target.value = ''

    return
  }

  pendingAttachment.value = file
  event.target.value = ''
}

async function submit() {
  const body = draft.value.trim()

  if (body === '' && !pendingAttachment.value) return

  emit('send', {
    body,
    attachment: pendingAttachment.value,
    client_message_id: newClientMessageId(),
    reply_to_message_id: replyTarget.value?.id ?? null,
  })

  draft.value = ''
  pendingAttachment.value = null
  replyTarget.value = null
  scrollToBottom()
}

function startEdit(message) {
  editing.value = message.id
  editBody.value = message.body ?? ''
}

function saveEdit() {
  const body = editBody.value.trim()

  if (body === '') return

  emit('edit', { messageId: editing.value, body })
  editing.value = null
}

function cancelEdit() {
  editing.value = null
  editBody.value = ''
}

function cancelReply() {
  replyTarget.value = null
}

// Le parent peut demander une réponse ciblée (clic sur « Répondre »).
defineExpose({
  setReplyTarget(message) {
    replyTarget.value = message
    scrollToBottom()
  },
})
</script>
