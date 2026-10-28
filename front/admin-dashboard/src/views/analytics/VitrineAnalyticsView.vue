<template>
  <div class="space-y-8 animate-fade-in">
    <!-- Header -->
    <div class="card p-8 relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-64 h-64 bg-brand-500/10 rounded-full blur-3xl"></div>

      <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
          <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white mb-2">
            {{ $t('vitrineAnalytics.title') }}
          </h1>
          <p class="text-slate-500 dark:text-slate-400 font-medium">
            {{ $t('vitrineAnalytics.subtitle') }}
          </p>
        </div>

        <div class="flex items-center gap-4">
          <select v-model.number="days" class="form-input py-2.5 w-auto" @change="load">
            <option :value="7">7 {{ $t('vitrineAnalytics.daysUnit') }}</option>
            <option :value="30">30 {{ $t('vitrineAnalytics.daysUnit') }}</option>
            <option :value="90">90 {{ $t('vitrineAnalytics.daysUnit') }}</option>
          </select>
          <button @click="load" :disabled="isLoading" class="btn-secondary py-2.5">
            <ArrowPathIcon :class="['h-5 w-5 mr-2', isLoading ? 'animate-spin' : '']" />
            {{ $t('vitrineAnalytics.refresh') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Bannière d'erreur + retry -->
    <div
      v-if="errorMessage"
      class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-900/30 dark:bg-red-950/20 dark:text-red-400"
      role="alert"
    >
      {{ errorMessage }}
      <button class="ml-3 underline font-bold" @click="load">{{ $t('vitrineAnalytics.retry') }}</button>
    </div>

    <!-- Rappel de mesure : pas de visiteurs uniques (choix de confidentialité) -->
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs font-semibold text-slate-600 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-400">
      {{ $t('vitrineAnalytics.privacyNote') }}
    </div>

    <div v-if="isLoading" class="py-12 text-center text-sm font-bold text-slate-400 uppercase tracking-widest">
      {{ $t('vitrineAnalytics.loading') }}
    </div>

    <template v-else-if="data">
      <!-- Métriques clés -->
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        <MetricCard
          :title="$t('vitrineAnalytics.metricViews')"
          :value="String(data.traffic?.views ?? 0)"
          icon="EyeIcon"
          color="blue"
        />
        <MetricCard
          :title="$t('vitrineAnalytics.metricPages')"
          :value="String(data.traffic?.pages ?? 0)"
          icon="DocumentTextIcon"
          color="purple"
        />
        <MetricCard
          :title="$t('vitrineAnalytics.metricDays')"
          :value="String(data.traffic?.days_tracked ?? 0)"
          icon="CalendarDaysIcon"
          color="green"
        />
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Top pages -->
        <div class="card p-6">
          <h2 class="text-lg font-black text-slate-900 dark:text-white mb-4">
            {{ $t('vitrineAnalytics.topPages') }}
          </h2>

          <p v-if="!data.top_pages?.length" class="text-sm text-slate-400 font-semibold">
            {{ $t('vitrineAnalytics.empty') }}
          </p>

          <table v-else class="w-full text-sm">
            <thead>
              <tr class="text-left text-xs uppercase tracking-widest text-slate-400">
                <th class="pb-2">{{ $t('vitrineAnalytics.page') }}</th>
                <th class="pb-2 text-right">{{ $t('vitrineAnalytics.views') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="page in data.top_pages"
                :key="page.path"
                class="border-t border-slate-100 dark:border-slate-800"
              >
                <td class="py-2 font-mono text-xs text-slate-700 dark:text-slate-300">{{ page.path }}</td>
                <td class="py-2 text-right font-bold text-slate-900 dark:text-white">{{ page.views }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Sources -->
        <div class="card p-6">
          <h2 class="text-lg font-black text-slate-900 dark:text-white mb-4">
            {{ $t('vitrineAnalytics.sources') }}
          </h2>

          <p v-if="!data.sources?.length" class="text-sm text-slate-400 font-semibold">
            {{ $t('vitrineAnalytics.empty') }}
          </p>

          <table v-else class="w-full text-sm">
            <thead>
              <tr class="text-left text-xs uppercase tracking-widest text-slate-400">
                <th class="pb-2">{{ $t('vitrineAnalytics.source') }}</th>
                <th class="pb-2 text-right">{{ $t('vitrineAnalytics.views') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="source in data.sources"
                :key="source.source"
                class="border-t border-slate-100 dark:border-slate-800"
              >
                <td class="py-2 font-semibold text-slate-700 dark:text-slate-300">
                  {{ source.source === 'direct' ? $t('vitrineAnalytics.direct') : source.source }}
                </td>
                <td class="py-2 text-right font-bold text-slate-900 dark:text-white">{{ source.views }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tunnel de conversion -->
      <div class="card p-6">
        <h2 class="text-lg font-black text-slate-900 dark:text-white mb-4">
          {{ $t('vitrineAnalytics.conversion') }}
        </h2>

        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-widest text-slate-400">
              <th class="pb-2">{{ $t('vitrineAnalytics.step') }}</th>
              <th class="pb-2 text-right">{{ $t('vitrineAnalytics.journeys') }}</th>
              <th class="pb-2 text-right">{{ $t('vitrineAnalytics.fromPrevious') }}</th>
              <th class="pb-2 text-right">{{ $t('vitrineAnalytics.fromStart') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="step in data.conversion"
              :key="step.step"
              class="border-t border-slate-100 dark:border-slate-800"
            >
              <td class="py-2 font-mono text-xs text-slate-700 dark:text-slate-300">{{ step.step }}</td>
              <td class="py-2 text-right font-bold text-slate-900 dark:text-white">{{ step.events }}</td>
              <td class="py-2 text-right text-slate-600 dark:text-slate-400">{{ rateLabel(step.rate_from_previous) }}</td>
              <td class="py-2 text-right text-slate-600 dark:text-slate-400">{{ rateLabel(step.rate_from_start) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Derniers leads vitrine (avant #7595 : écrits mais jamais relus) -->
      <div class="card p-6">
        <h2 class="text-lg font-black text-slate-900 dark:text-white mb-4">
          {{ $t('vitrineAnalytics.recentLeads') }}
        </h2>

        <p v-if="!data.recent_leads?.length" class="text-sm text-slate-400 font-semibold">
          {{ $t('vitrineAnalytics.empty') }}
        </p>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-xs uppercase tracking-widest text-slate-400">
                <th class="pb-2">{{ $t('vitrineAnalytics.leadDate') }}</th>
                <th class="pb-2">{{ $t('vitrineAnalytics.leadType') }}</th>
                <th class="pb-2">{{ $t('vitrineAnalytics.leadName') }}</th>
                <th class="pb-2">{{ $t('vitrineAnalytics.leadEmail') }}</th>
                <th class="pb-2">{{ $t('vitrineAnalytics.leadSource') }}</th>
                <th class="pb-2">{{ $t('vitrineAnalytics.leadStatus') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="lead in data.recent_leads"
                :key="lead.id"
                class="border-t border-slate-100 dark:border-slate-800"
              >
                <td class="py-2 text-xs text-slate-500 dark:text-slate-400">{{ formatDate(lead.created_at) }}</td>
                <td class="py-2 font-semibold text-slate-700 dark:text-slate-300">{{ lead.type }}</td>
                <td class="py-2 text-slate-700 dark:text-slate-300">{{ lead.name || '—' }}</td>
                <td class="py-2 text-slate-700 dark:text-slate-300">{{ lead.email }}</td>
                <td class="py-2 text-slate-700 dark:text-slate-300">{{ lead.source || '—' }}</td>
                <td class="py-2">
                  <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    {{ lead.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Période réellement couverte (l'API borne à 90 jours) -->
      <p class="text-xs text-slate-400 font-semibold text-right">
        {{ $t('vitrineAnalytics.period') }} : {{ data.period?.days }} {{ $t('vitrineAnalytics.daysUnit') }}
      </p>
    </template>
  </div>
</template>

<script setup>
// #7595 — analytique de la VITRINE (visites, top pages, sources, taux de
// passage par étape du tunnel, derniers leads). Écran DÉDIÉ : la route
// `/analytics` (AnalyticsView) ne montre que les KPI plateforme et ne doit pas
// être dupliquée — c'est une exigence explicite de l'issue.
//
// Données : GET /admin/vitrine/analytics (PlatformVitrineAnalyticsController).
// Aucune PII au-delà de ce que le prospect a lui-même soumis dans un formulaire.
import { onMounted, ref } from 'vue'
import { ArrowPathIcon } from '@heroicons/vue/24/outline'
import MetricCard from '@/components/analytics/MetricCard.vue'
import api from '@/services/api'

const data = ref(null)
const days = ref(30)
const isLoading = ref(true)
const errorMessage = ref('')

// `null` = le ratio n'existe pas (étape amont à zéro) → « — », jamais « 0 % ».
// Cette règle vient de l'API, on ne la recalcule pas ici pour éviter que les
// deux surfaces divergent.
function rateLabel(rate) {
  if (rate === null || rate === undefined) return '—'
  return `${Math.round(rate * 100)} %`
}

function formatDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString()
}

async function load() {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const res = await api.get('/admin/vitrine/analytics', {
      params: { days: days.value },
      _skipToast: true,
    })
    data.value = res.data?.data ?? null
  } catch (e) {
    errorMessage.value = e?.response?.data?.localized_message || e?.message || 'Erreur'
  } finally {
    isLoading.value = false
  }
}

onMounted(load)
</script>
