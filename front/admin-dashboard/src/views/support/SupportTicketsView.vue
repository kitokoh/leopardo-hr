<template>
  <div class="space-y-8 animate-fade-in">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">Centre support client</h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          Conversations ouvertes par les entreprises clientes, triées par priorité.
        </p>
        <div v-if="companyFilter.id" class="mt-3 inline-flex items-center gap-2 rounded-xl border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-black uppercase tracking-widest text-brand-700 dark:border-brand-800 dark:bg-brand-900/30 dark:text-brand-300">
          Filtre : {{ companyFilter.name || 'Entreprise sélectionnée' }}
          <button type="button" class="rounded-full p-0.5 hover:bg-brand-100 dark:hover:bg-brand-900/50" @click="clearCompanyFilter">
            <XMarkIcon class="h-3.5 w-3.5" />
          </button>
        </div>
      </div>
      <button class="btn-secondary py-2.5 shadow-glass-sm" :disabled="isLoading" @click="loadTickets">
        <ArrowPathIcon class="mr-2 h-4 w-4" :class="{ 'animate-spin': isLoading }" />
        Actualiser
      </button>
    </div>

    <!-- KPI Summary -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4 animate-slide-up">
      <StatsCard title="Ouverts" :value="statusCounts.open" icon="ChatBubbleBottomCenterTextIcon" color="blue" />
      <StatsCard title="En attente client" :value="statusCounts.pending" icon="ClockIcon" color="yellow" />
      <StatsCard title="Résolus" :value="statusCounts.resolved" icon="CheckCircleIcon" color="green" />
      <StatsCard title="Fermés" :value="statusCounts.closed" icon="XCircleIcon" color="red" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
      <!-- Ticket list -->
      <div class="card lg:col-span-2 animate-slide-up" style="animation-delay: 0.1s">
        <div class="flex flex-col gap-4 border-b border-slate-200/50 px-6 py-6 dark:border-slate-800/50 bg-slate-50/30 dark:bg-slate-900/20">
          <div class="flex flex-wrap gap-2 p-1 bg-slate-200/50 dark:bg-slate-800/50 rounded-2xl w-fit">
            <button
              v-for="option in statusFilters"
              :key="option.value"
              :class="[
                'px-3 py-1.5 text-xs font-black uppercase tracking-widest transition-all rounded-xl',
                activeStatus === option.value
                  ? 'bg-white dark:bg-slate-700 text-brand-600 dark:text-white shadow-glass-sm'
                  : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
              ]"
              @click="setStatusFilter(option.value)"
            >
              {{ option.label }}
            </button>
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="option in priorityFilters"
              :key="option.value"
              :class="[
                'px-3 py-1 text-[10px] font-black uppercase tracking-widest rounded-lg border transition',
                activePriority === option.value
                  ? 'border-brand-400 bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300'
                  : 'border-slate-200 text-slate-500 dark:border-slate-700'
              ]"
              @click="setPriorityFilter(option.value)"
            >
              {{ option.label }}
            </button>
          </div>
        </div>

        <div v-if="isLoading && tickets.length === 0" class="flex flex-col items-center justify-center p-16 gap-4">
          <div class="h-10 w-10 animate-spin rounded-full border-4 border-brand-500 border-t-transparent"></div>
        </div>

        <div v-else-if="tickets.length === 0" class="p-16 text-center">
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400">
            <InboxIcon class="h-7 w-7" />
          </div>
          <p class="mt-4 text-sm font-bold text-slate-400 uppercase tracking-widest">Aucun ticket</p>
        </div>

        <div v-else class="max-h-[640px] divide-y divide-slate-200/50 overflow-y-auto dark:divide-slate-800/50">
          <button
            v-for="ticket in tickets"
            :key="ticket.id"
            type="button"
            class="w-full p-4 text-left transition hover:bg-slate-50/70 dark:hover:bg-slate-800/40"
            :class="selectedTicket?.id === ticket.id ? 'bg-brand-50/60 dark:bg-brand-900/20' : ''"
            @click="selectTicket(ticket)"
          >
            <div class="flex items-center justify-between gap-2">
              <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ ticket.subject }}</p>
              <span :class="['shrink-0 rounded-lg border px-2 py-0.5 text-[9px] font-black uppercase tracking-widest', priorityClass(ticket.priority)]">
                {{ priorityLabel(ticket.priority) }}
              </span>
            </div>
            <p class="mt-1 truncate text-xs font-semibold text-slate-500 dark:text-slate-400">
              {{ ticket.company?.name || 'Entreprise inconnue' }}
            </p>
            <div class="mt-2 flex items-center justify-between">
              <span :class="['rounded-lg border px-2 py-0.5 text-[9px] font-black uppercase tracking-widest', statusClass(ticket.status)]">
                {{ statusLabel(ticket.status) }}
              </span>
              <span class="text-[10px] font-semibold text-slate-400">{{ formatDate(ticket.last_message_at) }}</span>
            </div>
          </button>
        </div>
      </div>

      <!-- Ticket detail / conversation -->
      <div class="card lg:col-span-3 animate-slide-up" style="animation-delay: 0.15s">
        <div v-if="!selectedTicket" class="flex h-full min-h-[400px] items-center justify-center p-16 text-center">
          <div>
            <ChatBubbleBottomCenterTextIcon class="mx-auto h-10 w-10 text-slate-300" />
            <p class="mt-4 text-sm font-bold text-slate-400 uppercase tracking-widest">
              Sélectionnez un ticket pour voir la conversation
            </p>
          </div>
        </div>

        <div v-else>
          <div class="flex flex-col gap-4 border-b border-slate-200/50 px-6 py-6 dark:border-slate-800/50">
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ selectedTicket.subject }}</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500 dark:text-slate-400">
                  {{ selectedTicket.company?.name }} · {{ selectedTicket.created_by?.name || 'Contact inconnu' }}
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <select
                  v-model="triageStatus"
                  class="form-select"
                  :disabled="isTriaging"
                  @change="applyTriage"
                >
                  <option v-for="s in statusFilters.filter((f) => f.value !== 'all')" :key="s.value" :value="s.value">
                    {{ s.label }}
                  </option>
                </select>
                <select
                  v-model="triagePriority"
                  class="form-select"
                  :disabled="isTriaging"
                  @change="applyTriage"
                >
                  <option v-for="p in priorityFilters.filter((f) => f.value !== 'all')" :key="p.value" :value="p.value">
                    {{ p.label }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <div ref="messagesContainer" class="max-h-[420px] space-y-4 overflow-y-auto p-6">
            <div
              v-for="message in selectedTicket.messages || []"
              :key="message.id"
              :class="['flex', message.from_platform ? 'justify-end' : 'justify-start']"
            >
              <div
                :class="[
                  'max-w-[75%] rounded-2xl px-4 py-3 text-sm shadow-sm',
                  message.from_platform
                    ? 'bg-brand-600 text-white'
                    : 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-slate-100'
                ]"
              >
                <p class="whitespace-pre-wrap leading-relaxed">{{ message.body }}</p>
                <p :class="['mt-1 text-[10px] font-semibold', message.from_platform ? 'text-brand-100' : 'text-slate-400']">
                  {{ message.author_name || (message.from_platform ? 'Équipe Leopardo' : 'Client') }} · {{ formatDate(message.created_at) }}
                </p>
              </div>
            </div>
          </div>

          <div class="border-t border-slate-200/50 p-4 dark:border-slate-800/50">
            <div class="flex gap-3">
              <textarea
                v-model="replyBody"
                rows="2"
                class="form-input flex-1 text-sm"
                placeholder="Répondre au client..."
                :disabled="isReplying"
              ></textarea>
              <button
                class="btn-primary self-end px-5 py-3"
                :disabled="isReplying || !replyBody.trim()"
                @click="sendReply"
              >
                <PaperAirplaneIcon class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import {
  ArrowPathIcon,
  ChatBubbleBottomCenterTextIcon,
  ClockIcon,
  CheckCircleIcon,
  XCircleIcon,
  InboxIcon,
  PaperAirplaneIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import { useLocaleStore } from '@/stores/locale'
import { toIntlLocale } from '@/i18n/index.js'

const toast = useToast()
const localeStore = useLocaleStore()
const route = useRoute()
const router = useRouter()

const isLoading = ref(false)
const isReplying = ref(false)
const isTriaging = ref(false)
const activeStatus = ref('all')
const activePriority = ref('all')
// PA2-ADM-003: when arriving from a company detail page via
// "Voir tous les tickets" (?company_id=...), scope the ticket list to that
// company only, and surface a chip so the admin can clear the filter.
const companyFilter = ref({
  id: typeof route.query.company_id === 'string' ? route.query.company_id : '',
  name: typeof route.query.company_name === 'string' ? route.query.company_name : '',
})
const tickets = ref([])
const selectedTicket = ref(null)
const replyBody = ref('')
const triageStatus = ref('open')
const triagePriority = ref('normal')
const statusCounts = ref({ open: 0, pending: 0, resolved: 0, closed: 0 })
const messagesContainer = ref(null)

const statusFilters = [
  { value: 'all', label: 'Tous' },
  { value: 'open', label: 'Ouverts' },
  { value: 'pending', label: 'En attente' },
  { value: 'resolved', label: 'Résolus' },
  { value: 'closed', label: 'Fermés' },
]

const priorityFilters = [
  { value: 'all', label: 'Toutes priorités' },
  { value: 'urgent', label: 'Urgent' },
  { value: 'high', label: 'Haute' },
  { value: 'normal', label: 'Normale' },
  { value: 'low', label: 'Basse' },
]

async function loadTickets() {
  isLoading.value = true

  try {
    const params = {}
    if (activeStatus.value !== 'all') params.status = activeStatus.value
    if (activePriority.value !== 'all') params.priority = activePriority.value
    if (companyFilter.value.id) params.company_id = companyFilter.value.id

    const response = await api.get('/platform/support-tickets', { params })
    tickets.value = response.data?.data || []
    statusCounts.value = response.data?.meta?.status_counts || statusCounts.value

    if (selectedTicket.value) {
      const stillPresent = tickets.value.find((t) => t.id === selectedTicket.value.id)
      if (!stillPresent) {
        selectedTicket.value = null
      }
    }
  } catch (error) {
    console.error('Failed to load support tickets:', error)
    toast.error('Impossible de charger les tickets de support.')
  } finally {
    isLoading.value = false
  }
}

async function selectTicket(ticket) {
  try {
    const response = await api.get(`/platform/support-tickets/${ticket.id}`)
    selectedTicket.value = response.data?.data || ticket
    triageStatus.value = selectedTicket.value.status
    triagePriority.value = selectedTicket.value.priority
    await nextTick()
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  } catch (error) {
    console.error('Failed to load ticket detail:', error)
    toast.error('Impossible de charger la conversation.')
  }
}

async function sendReply() {
  if (!selectedTicket.value || !replyBody.value.trim() || isReplying.value) return
  isReplying.value = true

  try {
    const response = await api.post(`/platform/support-tickets/${selectedTicket.value.id}/reply`, {
      message: replyBody.value.trim(),
    })
    selectedTicket.value = response.data?.data || selectedTicket.value
    replyBody.value = ''
    await loadTickets()
    await nextTick()
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  } catch (error) {
    console.error('Failed to reply to ticket:', error)
    toast.error('Envoi de la réponse impossible.')
  } finally {
    isReplying.value = false
  }
}

async function applyTriage() {
  if (!selectedTicket.value || isTriaging.value) return
  isTriaging.value = true

  try {
    await api.patch(`/platform/support-tickets/${selectedTicket.value.id}/triage`, {
      status: triageStatus.value,
      priority: triagePriority.value,
    })
    toast.success('Ticket mis à jour.')
    await loadTickets()
  } catch (error) {
    console.error('Failed to triage ticket:', error)
    toast.error('Mise à jour du ticket impossible.')
  } finally {
    isTriaging.value = false
  }
}

function setStatusFilter(status) {
  activeStatus.value = status
  loadTickets()
}

function clearCompanyFilter() {
  companyFilter.value = { id: '', name: '' }
  router.replace({ name: 'support-tickets' })
  loadTickets()
}

function setPriorityFilter(priority) {
  activePriority.value = priority
  loadTickets()
}

function statusClass(status) {
  const classes = {
    open: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800',
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800',
    resolved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
    closed: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
  }
  return classes[status] || classes.closed
}

function statusLabel(status) {
  const labels = { open: 'Ouvert', pending: 'En attente', resolved: 'Résolu', closed: 'Fermé' }
  return labels[status] || status
}

function priorityClass(priority) {
  const classes = {
    urgent: 'border-red-300 bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-300',
    high: 'border-orange-300 bg-orange-50 text-orange-700 dark:bg-orange-950/30 dark:text-orange-300',
    normal: 'border-slate-200 bg-slate-50 text-slate-600 dark:bg-slate-900/40 dark:text-slate-300',
    low: 'border-slate-200 bg-slate-50 text-slate-400 dark:bg-slate-900/40 dark:text-slate-500',
  }
  return classes[priority] || classes.normal
}

function priorityLabel(priority) {
  const labels = { urgent: 'Urgent', high: 'Haute', normal: 'Normale', low: 'Basse' }
  return labels[priority] || priority
}

function formatDate(value) {
  if (!value) return 'Non renseigné'

  return new Intl.DateTimeFormat(toIntlLocale(localeStore.current), {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(value))
}

onMounted(loadTickets)
</script>

<style scoped>
@reference '../../style.css';
.form-input {
  @apply block w-full rounded-2xl border border-slate-200 bg-white/50 px-4 py-3 text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-slate-800 dark:bg-slate-950/50 dark:text-white backdrop-blur-sm placeholder:text-slate-400 font-medium;
}
.form-select {
  @apply rounded-xl border border-slate-200 bg-white/70 px-3 py-2 text-xs font-bold uppercase tracking-widest text-slate-700 outline-none transition focus:border-brand-500 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-200;
}
</style>
