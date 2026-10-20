<template>
  <aside class="flex h-full min-h-0 w-full flex-col border-r border-slate-200 dark:border-slate-700 md:w-80 md:shrink-0">
    <div class="flex items-center justify-between gap-2 border-b border-slate-200 p-4 dark:border-slate-700">
      <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
        {{ t('chatHub.list.title') }}
      </h2>
      <span
        v-if="unreadCount > 0"
        class="rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-bold text-white"
        :aria-label="`${unreadCount} ${t('chatHub.list.unread')}`"
      >
        {{ unreadCount }}
      </span>
    </div>

    <div class="border-b border-slate-200 p-3 dark:border-slate-700">
      <label class="sr-only" for="chat-search">{{ t('chatHub.list.search') }}</label>
      <input
        id="chat-search"
        v-model="search"
        type="search"
        :placeholder="t('chatHub.list.search')"
        class="w-full rounded-lg border-slate-200 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
      />
    </div>

    <!-- Chargement -->
    <div v-if="loading" class="flex items-center justify-center gap-3 py-12 text-slate-400">
      <div class="h-4 w-4 animate-spin rounded-full border-2 border-indigo-500 border-t-transparent" />
      <span class="text-sm">{{ t('chatHub.common.loading') }}</span>
    </div>

    <!-- Erreur -->
    <div v-else-if="error" class="px-4 py-10 text-center" role="alert">
      <ExclamationTriangleIcon class="mx-auto h-8 w-8 text-red-400" />
      <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">
        {{ t('chatHub.list.loadError') }}
      </p>
      <button
        type="button"
        class="mt-3 rounded-full bg-indigo-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-indigo-700"
        @click="$emit('retry')"
      >
        {{ t('chatHub.common.retry') }}
      </button>
    </div>

    <!-- Vide -->
    <div
      v-else-if="filtered.length === 0"
      class="flex flex-col items-center gap-2 px-4 py-12 text-center text-slate-400"
    >
      <ChatBubbleLeftRightIcon class="h-8 w-8" />
      <p class="text-sm font-medium">{{ t('chatHub.list.empty') }}</p>
      <p class="text-xs">{{ t('chatHub.list.emptyHint') }}</p>
    </div>

    <!-- Liste -->
    <ul v-else class="min-h-0 flex-1 overflow-y-auto" role="listbox" :aria-label="t('chatHub.list.title')">
      <li v-for="thread in filtered" :key="thread.id">
        <button
          type="button"
          role="option"
          :aria-selected="thread.id === activeId"
          :class="[
            'flex w-full items-start gap-3 border-b border-slate-100 px-4 py-3 text-start transition glass-bg-hover dark:border-slate-800',
            thread.id === activeId ? 'bg-indigo-50 dark:bg-indigo-900/20' : ''
          ]"
          @click="$emit('select', thread)"
        >
          <span
            :class="[
              'mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold',
              thread.kind === 'group'
                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
            ]"
            aria-hidden="true"
          >
            {{ initialsFor(thread) }}
          </span>

          <span class="min-w-0 flex-1">
            <span class="flex items-center gap-2">
              <span
                :class="[
                  'truncate text-sm',
                  thread.unread
                    ? 'font-bold text-slate-900 dark:text-white'
                    : 'font-medium text-slate-700 dark:text-slate-200'
                ]"
              >
                {{ displayTitle(thread) }}
              </span>
              <span
                v-if="thread.kind === 'group'"
                class="shrink-0 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
              >
                {{ t('chatHub.list.kindGroup') }}
              </span>
            </span>
            <span class="mt-0.5 flex items-center justify-between gap-2">
              <span class="truncate text-xs text-slate-400">
                {{ thread.subject_type ? subjectLabel(thread) : t('chatHub.list.noSubject') }}
              </span>
              <span class="shrink-0 text-[11px] text-slate-400">{{ formatDate(thread.last_message_at) }}</span>
            </span>
          </span>

          <span
            v-if="thread.unread"
            class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full bg-indigo-600"
            :aria-label="t('chatHub.list.unread')"
          />
        </button>
      </li>
    </ul>
  </aside>
</template>

<script setup>
/**
 * #8754 [BOS-CHAT] — liste des conversations (chat horizontal, BC-13 COMMS).
 *
 * ⚠️ Composant du CHAT HUMAIN. L'assistant IA plateforme (Léa) vit dans
 * `@/views/chat/ChatView.vue` et n'a rien à voir : aucun historique partagé.
 */
import { computed, ref } from 'vue'
import { ChatBubbleLeftRightIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const props = defineProps({
  threads: { type: Array, default: () => [] },
  activeId: { type: Number, default: null },
  loading: { type: Boolean, default: false },
  error: { type: Boolean, default: false },
})

defineEmits(['select', 'retry'])

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const search = ref('')

const unreadCount = computed(() => props.threads.filter((thread) => thread.unread).length)

const filtered = computed(() => {
  const needle = search.value.trim().toLowerCase()

  if (needle === '') return props.threads

  return props.threads.filter((thread) => displayTitle(thread).toLowerCase().includes(needle))
})

function displayTitle(thread) {
  if (thread.title && String(thread.title).trim() !== '') return thread.title
  if (thread.kind === 'group') return t('chatHub.list.kindGroup')

  const other = thread.manager?.name || thread.employee?.name

  return other || t('chatHub.list.conversation')
}

function initialsFor(thread) {
  return displayTitle(thread)
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase()
}

function subjectLabel(thread) {
  return t(`chatHub.subjects.${thread.subject_type}`, thread.subject_type)
}

function formatDate(value) {
  if (!value) return ''

  try {
    return new Date(value).toLocaleDateString(localeStore.current, { day: '2-digit', month: '2-digit' })
  } catch {
    return ''
  }
}
</script>
