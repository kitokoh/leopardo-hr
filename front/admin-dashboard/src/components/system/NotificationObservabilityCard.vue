<template>
  <section class="card">
    <div class="flex items-center justify-between border-b border-slate-200/50 px-6 py-5 dark:border-slate-800/50">
      <div>
        <h3 class="text-xl font-bold text-slate-900 dark:text-white">Notifications &amp; Runbooks</h3>
        <p class="text-xs text-slate-400 mt-0.5">
          Échecs d'envoi (email/SMS/push/WhatsApp) tous clients confondus sur 24h, et procédures d'incident.
        </p>
      </div>
      <button
        @click="$emit('refresh')"
        :disabled="loading"
        class="p-2 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors disabled:opacity-50"
        title="Actualiser"
      >
        <ArrowPathIcon class="h-5 w-5" :class="{ 'animate-spin': loading }" />
      </button>
    </div>

    <div class="p-6 space-y-6">
      <!-- Alert banner -->
      <div
        v-if="data?.alerts?.notification_failures"
        class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-bold text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-400"
      >
        <ExclamationTriangleIcon class="h-5 w-5 flex-shrink-0" />
        {{ data?.totals?.failed }} notifications échouées sur {{ data?.window_hours }}h (seuil: {{ data?.thresholds?.notification_failures }}).
      </div>
      <div
        v-else-if="data"
        class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-bold text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-400"
      >
        <CheckCircleIcon class="h-5 w-5 flex-shrink-0" />
        Aucune alerte — le taux d'échec des notifications est sous contrôle.
      </div>

      <!-- Summary -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="rounded-xl border border-slate-200/60 dark:border-slate-800/60 p-3">
          <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Clients scannés</div>
          <div class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ data?.companies_scanned ?? '—' }}</div>
        </div>
        <div class="rounded-xl border border-slate-200/60 dark:border-slate-800/60 p-3">
          <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Événements ({{ data?.window_hours ?? 24 }}h)</div>
          <div class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ data?.totals?.events ?? '—' }}</div>
        </div>
        <div class="rounded-xl border border-slate-200/60 dark:border-slate-800/60 p-3">
          <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Échecs</div>
          <div
            class="mt-1 text-sm font-bold"
            :class="data?.alerts?.notification_failures ? 'text-red-600' : 'text-slate-900 dark:text-white'"
          >
            {{ data?.totals?.failed ?? '—' }}
          </div>
        </div>
        <div class="rounded-xl border border-slate-200/60 dark:border-slate-800/60 p-3">
          <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Taux d'échec</div>
          <div class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ formatRate(data?.totals?.failure_rate) }}</div>
        </div>
      </div>

      <!-- By channel -->
      <div v-if="data?.by_channel?.length">
        <h4 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Échecs par canal</h4>
        <div class="space-y-1.5">
          <div
            v-for="channel in data.by_channel"
            :key="channel.channel"
            class="flex items-center justify-between text-sm px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-800/50"
          >
            <span class="font-medium text-slate-700 dark:text-slate-300">{{ channel.channel }}</span>
            <span class="font-bold text-red-600">{{ channel.failed }}</span>
          </div>
        </div>
      </div>

      <!-- Recent failures -->
      <div v-if="data?.recent_failures?.length">
        <h4 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Échecs récents</h4>
        <div class="space-y-1.5">
          <div
            v-for="(failure, index) in data.recent_failures"
            :key="`${failure.company_id}-${failure.occurred_at}-${index}`"
            class="text-sm px-3 py-2 rounded-lg bg-red-50 dark:bg-red-950/20"
          >
            <div class="flex items-center justify-between">
              <span class="font-bold text-red-700 dark:text-red-400">{{ failure.company_name }} · {{ failure.channel }}</span>
              <span class="text-slate-500 dark:text-slate-400 text-xs">{{ formatTime(failure.occurred_at) }}</span>
            </div>
            <div class="text-xs text-slate-600 dark:text-slate-400 truncate mt-0.5">
              {{ failure.template_key || 'template inconnu' }}<span v-if="failure.error_message"> — {{ failure.error_message }}</span>
            </div>
          </div>
        </div>
      </div>
      <div v-else-if="data" class="text-sm text-slate-500 dark:text-slate-400">
        Aucun échec de notification sur les dernières {{ data?.window_hours ?? 24 }}h.
      </div>

      <!-- Runbooks -->
      <div v-if="data?.runbooks?.length">
        <h4 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Runbooks opérationnels</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
          <a
            v-for="runbook in data.runbooks"
            :key="runbook.key"
            :href="runbookUrl(runbook.path)"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center gap-2 text-sm px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-800/50 text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300 font-bold transition-colors"
          >
            <BookOpenIcon class="h-4 w-4 flex-shrink-0" />
            {{ runbook.title }}
          </a>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ArrowPathIcon, ExclamationTriangleIcon, CheckCircleIcon, BookOpenIcon } from '@heroicons/vue/24/outline'
import { useLocaleStore } from '@/stores/locale'
import { toIntlLocale } from '@/i18n/index.js'

const localeStore = useLocaleStore()

defineProps({
  data: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['refresh'])

// Runbooks live in the repository, not on a public docs host — link to the
// GitHub-rendered Markdown so a super-admin can open them straight from
// the dashboard without needing repo access on their own machine.
const REPO_BLOB_BASE = 'https://github.com/kitokoh/leopardo-hr/blob/main/'

function runbookUrl(path) {
  return `${REPO_BLOB_BASE}${path}`
}

function formatRate(value) {
  if (value === null || value === undefined) return '—'
  return new Intl.NumberFormat(toIntlLocale(localeStore.current), {
    style: 'percent',
    maximumFractionDigits: 1,
  }).format(Number(value))
}

function formatTime(value) {
  if (!value) return 'Jamais'

  const date = new Date(value)
  const now = new Date()
  const diff = now - date

  if (diff < 60000) return "À l'instant"
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h`
  return date.toLocaleDateString(toIntlLocale(localeStore.current), {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>
