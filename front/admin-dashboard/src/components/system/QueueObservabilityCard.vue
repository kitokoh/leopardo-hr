<template>
  <section class="card">
    <div class="flex items-center justify-between border-b border-slate-200/50 px-6 py-5 dark:border-slate-800/50">
      <div>
        <h3 class="text-xl font-bold text-slate-900 dark:text-white">Observabilité Redis / Jobs</h3>
        <p class="text-xs text-slate-400 mt-0.5">
          Profondeur des files, jobs échoués et dernière exécution des tâches planifiées.
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
      <!-- Alert banners -->
      <div v-if="hasAnyAlert" class="space-y-2">
        <div
          v-if="data?.alerts?.redis_down"
          class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-bold text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-400"
        >
          <ExclamationTriangleIcon class="h-5 w-5 flex-shrink-0" />
          Redis inaccessible — les files ne peuvent pas être traitées.
        </div>
        <div
          v-if="data?.alerts?.queue_depth"
          class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-bold text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-400"
        >
          <ExclamationTriangleIcon class="h-5 w-5 flex-shrink-0" />
          Profondeur de file anormalement élevée (seuil: {{ data?.thresholds?.queue_depth }}).
        </div>
        <div
          v-if="data?.alerts?.failed_jobs"
          class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-bold text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-400"
        >
          <ExclamationTriangleIcon class="h-5 w-5 flex-shrink-0" />
          {{ data?.failed_jobs?.count }} jobs échoués (seuil: {{ data?.thresholds?.failed_jobs }}).
        </div>
        <div
          v-if="data?.alerts?.stale_tasks"
          class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-bold text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-400"
        >
          <ExclamationTriangleIcon class="h-5 w-5 flex-shrink-0" />
          Une ou plusieurs tâches planifiées n'ont pas tourné depuis plus de
          {{ data?.thresholds?.stale_task_hours }}h.
        </div>
      </div>
      <div
        v-else-if="data"
        class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-bold text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-400"
      >
        <CheckCircleIcon class="h-5 w-5 flex-shrink-0" />
        Aucune alerte — Redis, files et tâches planifiées sont sains.
      </div>

      <!-- Redis + queue summary -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="rounded-xl border border-slate-200/60 dark:border-slate-800/60 p-3">
          <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Redis</div>
          <div class="mt-1 flex items-center gap-1.5">
            <span :class="['h-2 w-2 rounded-full', data?.redis?.ok ? 'bg-emerald-500' : 'bg-red-500']"></span>
            <span class="text-sm font-bold" :class="data?.redis?.ok ? 'text-emerald-600' : 'text-red-600'">
              {{ data?.redis?.ok ? (data?.redis?.latency_ms + ' ms') : (data?.redis?.status || 'indisponible') }}
            </span>
          </div>
        </div>
        <div class="rounded-xl border border-slate-200/60 dark:border-slate-800/60 p-3">
          <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Profondeur totale</div>
          <div class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ data?.queue_total_depth ?? 0 }}</div>
        </div>
        <div class="rounded-xl border border-slate-200/60 dark:border-slate-800/60 p-3">
          <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Jobs échoués</div>
          <div
            class="mt-1 text-sm font-bold"
            :class="data?.alerts?.failed_jobs ? 'text-red-600' : 'text-slate-900 dark:text-white'"
          >
            {{ data?.failed_jobs?.count ?? '—' }}
          </div>
        </div>
        <div class="rounded-xl border border-slate-200/60 dark:border-slate-800/60 p-3">
          <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Connexion file</div>
          <div class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ data?.queue_connection ?? '—' }}</div>
        </div>
      </div>

      <!-- Per-queue depth -->
      <div v-if="data?.queues?.length">
        <h4 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Files</h4>
        <div class="space-y-1.5">
          <div
            v-for="queue in data.queues"
            :key="queue.name"
            class="flex items-center justify-between text-sm px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-800/50"
          >
            <span class="font-medium text-slate-700 dark:text-slate-300">{{ queue.name }}</span>
            <span :class="['font-bold', queue.ok ? 'text-slate-900 dark:text-white' : 'text-red-600']">
              {{ queue.ok ? queue.depth : 'indisponible' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Scheduled tasks last run -->
      <div v-if="data?.scheduled_tasks?.length">
        <h4 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Dernière exécution des tâches planifiées</h4>
        <div class="space-y-1.5">
          <div
            v-for="task in data.scheduled_tasks"
            :key="task.name"
            class="flex items-center justify-between text-sm px-3 py-2 rounded-lg"
            :class="task.is_stale ? 'bg-amber-50 dark:bg-amber-950/20' : 'bg-slate-50 dark:bg-slate-800/50'"
          >
            <span class="font-medium text-slate-700 dark:text-slate-300 truncate mr-3">{{ task.name }}</span>
            <div class="flex items-center gap-2 flex-shrink-0">
              <span
                :class="[
                  'px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest',
                  task.status === 'success' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' :
                  task.status === 'failed' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' :
                  'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                ]"
              >
                {{ task.status }}
              </span>
              <span v-if="task.is_stale" class="text-[10px] font-black uppercase tracking-widest text-amber-600">stale</span>
              <span class="text-slate-500 dark:text-slate-400">{{ formatTime(task.finished_at || task.started_at) }}</span>
            </div>
          </div>
        </div>
      </div>
      <div v-else-if="data" class="text-sm text-slate-500 dark:text-slate-400">
        Aucune tâche planifiée n'a encore été enregistrée.
      </div>

      <!-- Recent failed jobs -->
      <div v-if="data?.failed_jobs?.recent?.length">
        <h4 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Jobs échoués récents</h4>
        <div class="space-y-1.5">
          <div
            v-for="job in data.failed_jobs.recent"
            :key="job.id"
            class="text-sm px-3 py-2 rounded-lg bg-red-50 dark:bg-red-950/20"
          >
            <div class="flex items-center justify-between">
              <span class="font-bold text-red-700 dark:text-red-400">{{ job.queue }}</span>
              <span class="text-slate-500 dark:text-slate-400 text-xs">{{ formatTime(job.failed_at) }}</span>
            </div>
            <div class="text-xs text-slate-600 dark:text-slate-400 truncate mt-0.5">{{ job.exception }}</div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { ArrowPathIcon, ExclamationTriangleIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import { useLocaleStore } from '@/stores/locale'
import { toIntlLocale } from '@/i18n/index.js'

const localeStore = useLocaleStore()

const props = defineProps({
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

const hasAnyAlert = computed(() => {
  const alerts = props.data?.alerts
  return !!alerts && Object.values(alerts).some(Boolean)
})

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
