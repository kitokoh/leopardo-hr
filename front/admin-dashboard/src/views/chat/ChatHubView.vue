<template>
  <div class="space-y-4 animate-fade-in">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-white sm:text-4xl">
          {{ t('chatHub.title') }}
        </h1>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400 sm:text-base">
          {{ t('chatHub.subtitle') }}
        </p>
      </div>
      <button
        type="button"
        class="rounded-full border border-slate-200 px-4 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300"
        @click="reload"
      >
        {{ t('chatHub.common.refresh') }}
      </button>
    </div>

    <!-- Contexte tenant manquant : session plateforme hors impersonation.
         Même pattern honnête que TravelGate (#4710) : on n'affiche pas une
         fausse erreur serveur pour un simple défaut de contexte. -->
    <div
      v-if="tenantMissing"
      class="glass-card rounded-2xl p-8 text-center"
      role="status"
    >
      <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-sky-100 dark:bg-sky-900/30">
        <BuildingOfficeIcon class="h-6 w-6 text-sky-600 dark:text-sky-400" />
      </div>
      <h2 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">
        {{ t('chatHub.tenantRequired.title') }}
      </h2>
      <p class="mx-auto mt-2 max-w-md text-sm text-slate-500 dark:text-slate-400">
        {{ t('chatHub.tenantRequired.body') }}
      </p>
    </div>

    <!-- Feature désactivée pour le tenant (403 module) -->
    <div
      v-else-if="forbidden"
      class="glass-card rounded-2xl p-8 text-center"
      role="status"
    >
      <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/30">
        <LockClosedIcon class="h-6 w-6 text-amber-600 dark:text-amber-400" />
      </div>
      <h2 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">
        {{ t('chatHub.forbidden.title') }}
      </h2>
      <p class="mx-auto mt-2 max-w-md text-sm text-slate-500 dark:text-slate-400">
        {{ t('chatHub.forbidden.body') }}
      </p>
    </div>

    <!-- Chat -->
    <div
      v-else
      class="flex h-[calc(100vh-240px)] min-h-[28rem] overflow-hidden rounded-2xl glass-card shadow"
    >
      <conversation-list
        :threads="threads"
        :active-id="activeThread?.id ?? null"
        :loading="threadsLoading"
        :error="threadsError"
        :class="['md:flex', mobilePane === 'list' ? 'flex' : 'hidden']"
        @select="openThread"
        @retry="loadThreads"
      />

      <conversation-panel
        v-if="activeThread"
        ref="panel"
        :thread="activeThread"
        :messages="messages"
        :current-employee-id="currentEmployeeId"
        :loading="messagesLoading"
        :loading-older="loadingOlder"
        :has-more="hasMore"
        :error="messagesError"
        :offline="offline"
        :sending="sending"
        :send-error="sendError"
        :class="['md:flex', mobilePane === 'thread' ? 'flex' : 'hidden']"
        @send="onSend"
        @load-older="loadOlder"
        @retry="loadMessages"
        @edit="onEdit"
        @delete="onDelete"
        @report="onReport"
        @download="onDownload"
      />

      <div
        v-else
        class="flex flex-1 flex-col items-center justify-center gap-2 px-6 text-center text-slate-400"
        :class="mobilePane === 'thread' ? 'flex' : 'hidden md:flex'"
      >
        <ChatBubbleLeftRightIcon class="h-12 w-12" />
        <p class="text-sm font-medium">{{ t('chatHub.thread.selectTitle') }}</p>
        <p class="text-xs">{{ t('chatHub.thread.selectHint') }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * #8754 [BOS-CHAT] — écran du chat horizontal (BC-13 COMMS).
 *
 * Extension du module `Notification` existant : ce fichier ne crée AUCUN
 * second système de messagerie, d'auth, de notification ou de fichiers. Il
 * consomme les endpoints `/conversations/*` déjà livrés (P1→P5) et s'appuie
 * sur `@/lib/chat-api.js` (miroir du client Next.js validé).
 *
 * ⚠️ Chat HUMAIN. `@/views/chat/ChatView.vue` est l'assistant IA (Léa) :
 * deux produits distincts, aucun historique partagé, aucune action métier
 * déclenchée par une instruction écrite dans un message.
 *
 * Autorisation : entièrement serveur. Un non-participant reçoit 404 (jamais
 * 403 : un 403 confirmerait l'existence du fil à un tiers). Aucun filtre
 * d'accès n'est fait ici — l'UI ne fait que refléter ce que l'API renvoie.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  BuildingOfficeIcon,
  ChatBubbleLeftRightIcon,
  LockClosedIcon,
} from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useConversationPolling } from '@/composables/useChatPolling'
import {
  attachmentUrl,
  deleteMessage,
  editMessage,
  fetchMessages,
  fetchThreads,
  mergeMessages,
  reportMessage,
  sendMessage,
} from '@/lib/chat-api'
import ConversationList from '@/components/chat/conversation-list.vue'
import ConversationPanel from '@/components/chat/conversation-panel.vue'

const localeStore = useLocaleStore()
const toast = useToast()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const threads = ref([])
const threadsLoading = ref(true)
const threadsError = ref(false)
const tenantMissing = ref(false)
const forbidden = ref(false)

const activeThread = ref(null)
const messages = ref([])
const messagesLoading = ref(false)
const messagesError = ref(false)
const loadingOlder = ref(false)
const hasMore = ref(false)
const nextBeforeSeq = ref(null)
const lastSeq = ref(0)

const sending = ref(false)
const sendError = ref('')

const currentEmployeeId = ref(null)
/** Sur mobile, une seule colonne visible à la fois : liste OU fil. */
const mobilePane = ref('list')

const panel = ref(null)

const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine !== false)

const polling = useConversationPolling({
  threadId: computed(() => activeThread.value?.id ?? 0),
  lastSeq: computed(() => lastSeq.value),
  messages,
  enabled: computed(() => activeThread.value !== null && !messagesError.value),
})

const offline = computed(() => !online.value || polling.offline.value)

/**
 * Identité de l'acteur : sert UNIQUEMENT à aligner les bulles (les miennes à
 * droite) et à montrer les boutons d'édition. Ce n'est pas un contrôle
 * d'accès — le serveur réautorise chaque écriture.
 */
async function loadIdentity() {
  try {
    const response = await api.get('/auth/me', { _skipAuthRedirect: true })
    const payload = response.data?.data ?? response.data

    currentEmployeeId.value = payload?.id ?? payload?.employee?.id ?? null
  } catch {
    // Sans identité, l'UI reste utilisable (aucune bulle n'est « mienne »),
    // mais les boutons d'édition sont masqués. Pas d'échec bloquant.
    currentEmployeeId.value = null
  }
}

async function loadThreads() {
  threadsLoading.value = true
  threadsError.value = false
  tenantMissing.value = false
  forbidden.value = false

  try {
    threads.value = await fetchThreads()

    // Rouvre le fil courant s'il existe encore après un rafraîchissement.
    if (activeThread.value) {
      const refreshed = threads.value.find((thread) => thread.id === activeThread.value.id)

      if (refreshed) activeThread.value = refreshed
    }
  } catch (error) {
    const status = error?.response?.status

    if (status === 401) {
      // Session plateforme sans contexte tenant : ce n'est pas une panne.
      tenantMissing.value = true
    } else if (status === 403) {
      forbidden.value = true
    } else {
      threadsError.value = true
    }
  } finally {
    threadsLoading.value = false
  }
}

async function openThread(thread) {
  activeThread.value = thread
  mobilePane.value = 'thread'
  messages.value = []
  lastSeq.value = 0
  nextBeforeSeq.value = null
  hasMore.value = false
  sendError.value = ''

  await loadMessages()

  // Marque le fil comme lu côté serveur : `GET /conversations/{id}` appelle
  // `markReadFor()`. On rafraîchit ensuite la pastille de non-lus.
  try {
    await api.get(`/conversations/${thread.id}`, { _skipAuthRedirect: true })
    thread.unread = false
  } catch {
    // Le compteur de non-lus est un confort : un échec ici ne bloque pas.
  }

  // Remise à zéro explicite AVANT `start()` : le watch sur `activeThread`
  // la fait aussi, mais l'ordre d'exécution d'un watcher n'est pas garanti
  // par rapport à cette fonction asynchrone — un curseur hérité du fil
  // précédent sauterait des messages (bug silencieux : l'UI paraît marcher).
  polling.resetCursor(0)
  polling.syncCursor(lastSeq.value)
  polling.start()
}

async function loadMessages() {
  if (!activeThread.value) return

  messagesLoading.value = true
  messagesError.value = false

  try {
    const page = await fetchMessages(activeThread.value.id, {})

    messages.value = page.data
    lastSeq.value = page.meta.last_seq ?? 0
    hasMore.value = page.meta.has_more
    nextBeforeSeq.value = page.meta.next_before_seq
    polling.syncCursor(lastSeq.value)
  } catch (error) {
    // 404 = fil inexistant OU non-participant (le serveur ne distingue pas,
    // volontairement). On affiche l'erreur sans révéler laquelle des deux.
    messagesError.value = error?.response?.status !== 404
  } finally {
    messagesLoading.value = false
  }
}

async function loadOlder() {
  if (!activeThread.value || nextBeforeSeq.value == null || loadingOlder.value) return

  loadingOlder.value = true

  try {
    const page = await fetchMessages(activeThread.value.id, { before_seq: nextBeforeSeq.value })

    // Les messages plus anciens se placent AVANT : la fusion par `id` + tri
    // sur `seq` garantit l'ordre quel que soit l'ordre d'arrivée.
    messages.value = mergeMessages(messages.value, page.data)
    hasMore.value = page.meta.has_more
    nextBeforeSeq.value = page.meta.next_before_seq
  } catch {
    toast.error(t('chatHub.thread.loadError'))
  } finally {
    loadingOlder.value = false
  }
}

async function onSend(payload) {
  // Le composant signale une pièce trop lourde sans appel réseau.
  if (payload?.error === 'too-large') {
    sendError.value = t('chatHub.thread.attachmentTooLarge')

    return
  }

  if (!activeThread.value || sending.value) return

  sending.value = true
  sendError.value = ''

  try {
    const result = await sendMessage(activeThread.value.id, payload)

    if (result.message) {
      messages.value = mergeMessages(messages.value, [result.message])
      lastSeq.value = Math.max(lastSeq.value, result.message.seq ?? lastSeq.value)
      polling.syncCursor(lastSeq.value)
    }

    // `created === false` : le serveur a reconnu un rejeu (200). Aucun doublon
    // n'a été créé — c'est le comportement voulu, pas une erreur.
  } catch (error) {
    const status = error?.response?.status

    if (status === 404) {
      sendError.value = t('chatHub.thread.notParticipant')
    } else if (status === 429) {
      sendError.value = t('chatHub.thread.rateLimited')
    } else if (status === 422) {
      sendError.value = t('chatHub.thread.invalidMessage')
    } else {
      // Hors ligne : le message n'est PAS perdu côté client (le brouillon est
      // restauré par l'appelant au prochain essai) — on le dit clairement.
      sendError.value = offline.value
        ? t('chatHub.thread.offlineSendFailed')
        : t('chatHub.thread.sendFailed')
    }
  } finally {
    sending.value = false
  }
}

async function onEdit({ messageId, body }) {
  if (!activeThread.value) return

  try {
    const updated = await editMessage(activeThread.value.id, messageId, body)

    if (updated) {
      messages.value = messages.value.map((message) => (message.id === messageId ? updated : message))
    }
  } catch (error) {
    toast.error(
      error?.response?.status === 403
        ? t('chatHub.thread.editForbidden')
        : t('chatHub.thread.editFailed'),
    )
  }
}

async function onDelete(message) {
  if (!activeThread.value) return
  if (!window.confirm(t('chatHub.thread.deleteConfirm'))) return

  try {
    await deleteMessage(activeThread.value.id, message.id)

    // Suppression logique côté serveur : on ne retire PAS la ligne (le `seq`
    // doit rester visible pour ne pas percer l'ordre), on la marque supprimée.
    messages.value = messages.value.map((item) =>
      item.id === message.id ? { ...item, deleted: true, body: '', attachment: null } : item,
    )
  } catch {
    toast.error(t('chatHub.thread.deleteFailed'))
  }
}

async function onReport(message) {
  if (!activeThread.value) return

  const reason = window.prompt(
    `${t('chatHub.thread.reportPrompt')} (${t('chatHub.report.reasonsHint')})`,
    'inappropriate_content',
  )

  if (!reason) return

  try {
    await reportMessage(activeThread.value.id, message.id, reason, null)
    toast.success(t('chatHub.thread.reportSent'))
  } catch (error) {
    toast.error(
      error?.response?.status === 429
        ? t('chatHub.thread.rateLimited')
        : t('chatHub.thread.reportFailed'),
    )
  }
}

/**
 * Téléchargement d'une pièce jointe.
 *
 * Le fichier est servi par une route AUTHENTIFIÉE : on ne peut pas poser un
 * `<a href>` nu (le token n'est pas dans l'URL), donc on récupère le binaire
 * via le client HTTP puis on déclenche le téléchargement local. Un 410 signifie
 * que le message existe mais que le fichier a disparu (risque R1 : disque
 * éphémère redéployé) — on le dit sans ambiguïté.
 */
async function onDownload(message) {
  if (!activeThread.value) return

  try {
    const response = await api.get(attachmentUrl(activeThread.value.id, message.id), {
      responseType: 'blob',
      _skipAuthRedirect: true,
    })

    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')

    link.href = url
    link.download = message.attachment?.original_name || 'piece-jointe'
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  } catch (error) {
    const status = error?.response?.status

    if (status === 410) {
      toast.error(t('chatHub.thread.attachmentGone'))
    } else if (status === 404) {
      toast.error(t('chatHub.thread.attachmentForbidden'))
    } else {
      toast.error(t('chatHub.thread.attachmentFailed'))
    }
  }
}

function reload() {
  void loadThreads()

  if (activeThread.value) void loadMessages()
}

function setOnline(value) {
  online.value = value
}

// Références stables : `removeEventListener` ne reconnaît pas une lambda
// recréée, donc les écouteurs ajoutés ici fuyaient à chaque démontage.
const onBrowserOnline = () => setOnline(true)
const onBrowserOffline = () => setOnline(false)

onMounted(async () => {
  await Promise.all([loadThreads(), loadIdentity()])

  window.addEventListener('online', onBrowserOnline)
  window.addEventListener('offline', onBrowserOffline)
})

onBeforeUnmount(() => {
  polling.stop()
  window.removeEventListener('online', onBrowserOnline)
  window.removeEventListener('offline', onBrowserOffline)
})

watch(
  () => activeThread.value?.id,
  (next, previous) => {
    if (next === previous) return

    // Nouveau fil : on arrête le polling de l'ancien. `openThread()` relance
    // ensuite le polling ET remet le curseur à zéro — sans cette remise à
    // zéro, un fil dont les `seq` sont plus petits que ceux du précédent ne
    // recevrait plus jamais de messages (`after_seq` resterait au-dessus).
    polling.stop()
    polling.resetCursor(0)
  },
)
</script>
