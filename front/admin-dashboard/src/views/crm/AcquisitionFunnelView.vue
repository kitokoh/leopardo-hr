<template>
  <div class="space-y-8 animate-fade-in">
    <!-- Header -->
    <div class="card p-8 relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-64 h-64 bg-brand-500/10 rounded-full blur-3xl"></div>

      <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
          <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white mb-2">
            {{ $t('funnelStats.title') }}
          </h1>
          <p class="text-slate-500 dark:text-slate-400 font-medium">
            {{ $t('funnelStats.subtitle') }}
          </p>
        </div>

        <div class="flex items-center gap-4">
          <select v-model.number="days" class="form-input py-2.5 w-auto" @change="load">
            <option :value="7">7 {{ $t('funnelStats.daysUnit') }}</option>
            <option :value="30">30 {{ $t('funnelStats.daysUnit') }}</option>
            <option :value="90">90 {{ $t('funnelStats.daysUnit') }}</option>
          </select>
          <button @click="load" :disabled="isLoading" class="btn-secondary py-2.5">
            <ArrowPathIcon :class="['h-5 w-5 mr-2', isLoading ? 'animate-spin' : '']" />
            {{ $t('funnelStats.refresh') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Alerte livraison OTP (#7496 : mailer en échec = chute vérifiés/envoyés) -->
    <div
      v-if="otpAlert?.triggered"
      class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm font-semibold text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-300"
      role="alert"
    >
      {{ $t('funnelStats.otpAlert') }}
      ({{ otpAlert.verified_today }}/{{ otpAlert.sent_today }})
    </div>

    <!-- Bannière d'erreur + retry -->
    <div
      v-if="errorMessage"
      class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-900/30 dark:bg-red-950/20 dark:text-red-400"
      role="alert"
    >
      {{ errorMessage }}
      <button class="ml-3 underline font-bold" @click="load">{{ $t('funnelStats.retry') }}</button>
    </div>

    <div v-if="isLoading" class="py-12 text-center text-sm font-bold text-slate-400 uppercase tracking-widest">
      {{ $t('funnelStats.loading') }}
    </div>

    <template v-else-if="data">
      <!-- Métriques clés -->
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        <MetricCard
          :title="$t('funnelStats.metricJourneys')"
          :value="String(data.totals.journeys ?? 0)"
          icon="UsersIcon"
          color="blue"
        />
        <MetricCard
          :title="$t('funnelStats.metricProvisioned')"
          :value="String(data.totals.provisioned ?? 0)"
          icon="RocketLaunchIcon"
          color="green"
        />
        <MetricCard
          :title="$t('funnelStats.metricConversionRate')"
          :value="conversionRateLabel"
          icon="ArrowTrendingUpIcon"
          color="purple"
        />
      </div>

      <!-- Taux de passage par étape -->
      <div class="card p-8 animate-slide-up">
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-1">{{ $t('funnelStats.stepsTitle') }}</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">{{ $t('funnelStats.stepsHint') }}</p>

        <div v-if="maxJourneys === 0" class="py-8 text-center">
          <InformationCircleIcon class="mx-auto h-10 w-10 text-slate-300" />
          <p class="mt-3 text-sm font-medium text-slate-500">{{ $t('funnelStats.empty') }}</p>
        </div>
        <ul v-else class="space-y-3">
          <li v-for="step in data.steps" :key="step.event" class="flex items-center gap-3">
            <span class="w-56 truncate text-xs font-semibold text-slate-600 dark:text-slate-300" :title="step.event">
              {{ step.event }}
            </span>
            <div class="h-3 flex-1 rounded-full bg-slate-100 dark:bg-slate-800">
              <div
                class="h-3 rounded-full bg-brand-500 transition-all"
                :style="{ width: journeyShare(step.journeys) }"
              ></div>
            </div>
            <span class="w-14 text-right text-xs font-bold text-slate-700 dark:text-slate-200">{{ step.journeys }}</span>
            <span class="w-16 text-right text-xs font-medium text-slate-400">
              {{ step.rate_from_previous === null ? '—' : `${Math.round(step.rate_from_previous * 100)} %` }}
            </span>
          </li>
        </ul>
      </div>

      <!-- Conversion par jour -->
      <div class="card p-8 animate-slide-up">
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-1">{{ $t('funnelStats.byDayTitle') }}</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">{{ $t('funnelStats.byDayHint') }}</p>

        <div v-if="data.by_day.length === 0" class="py-8 text-center">
          <InformationCircleIcon class="mx-auto h-10 w-10 text-slate-300" />
          <p class="mt-3 text-sm font-medium text-slate-500">{{ $t('funnelStats.empty') }}</p>
        </div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-widest text-slate-400">
              <th class="pb-3 font-bold">{{ $t('funnelStats.dateColumn') }}</th>
              <th class="pb-3 font-bold">{{ $t('funnelStats.viewsColumn') }}</th>
              <th class="pb-3 font-bold">{{ $t('funnelStats.provisionedColumn') }}</th>
              <th class="pb-3 font-bold">{{ $t('funnelStats.conversionColumn') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200/50 dark:divide-slate-800/50">
            <tr v-for="row in data.by_day" :key="row.date">
              <td class="py-3 font-semibold text-slate-800 dark:text-slate-200">{{ row.date }}</td>
              <td class="py-3 text-slate-600 dark:text-slate-300">{{ row.steps.signup_view ?? 0 }}</td>
              <td class="py-3 text-slate-600 dark:text-slate-300">{{ row.steps.space_provisioned ?? 0 }}</td>
              <td class="py-3 font-bold text-brand-600 dark:text-brand-400">{{ Math.round((row.conversion_rate ?? 0) * 100) }} %</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Conversion par source -->
      <div class="card p-8 animate-slide-up">
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-1">{{ $t('funnelStats.bySourceTitle') }}</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">{{ $t('funnelStats.bySourceHint') }}</p>

        <div v-if="data.by_source.length === 0" class="py-8 text-center">
          <InformationCircleIcon class="mx-auto h-10 w-10 text-slate-300" />
          <p class="mt-3 text-sm font-medium text-slate-500">{{ $t('funnelStats.empty') }}</p>
        </div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-widest text-slate-400">
              <th class="pb-3 font-bold">{{ $t('funnelStats.sourceColumn') }}</th>
              <th class="pb-3 font-bold">{{ $t('funnelStats.viewsColumn') }}</th>
              <th class="pb-3 font-bold">{{ $t('funnelStats.provisionedColumn') }}</th>
              <th class="pb-3 font-bold">{{ $t('funnelStats.conversionColumn') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200/50 dark:divide-slate-800/50">
            <tr v-for="row in data.by_source" :key="row.source">
              <td class="py-3 font-semibold text-slate-800 dark:text-slate-200">{{ row.source }}</td>
              <td class="py-3 text-slate-600 dark:text-slate-300">{{ row.steps.signup_view ?? 0 }}</td>
              <td class="py-3 text-slate-600 dark:text-slate-300">{{ row.steps.space_provisioned ?? 0 }}</td>
              <td class="py-3 font-bold text-brand-600 dark:text-brand-400">{{ Math.round((row.conversion_rate ?? 0) * 100) }} %</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup>
// #7496 — dashboard des conversions du funnel d'acquisition : taux de passage
// par étape (signup_view → space_provisioned), conversion par jour et par
// source, alerte livraison OTP. Données : GET /admin/funnel/stats
// (PlatformAcquisitionFunnelController, table acquisition_funnel_events).
import { computed, onMounted, ref } from 'vue'
import { ArrowPathIcon, InformationCircleIcon } from '@heroicons/vue/24/outline'
import MetricCard from '@/components/analytics/MetricCard.vue'
import api from '@/services/api'

const data = ref(null)
const days = ref(30)
const isLoading = ref(true)
const errorMessage = ref('')

const conversionRateLabel = computed(() => {
  const rate = data.value?.totals?.conversion_rate ?? 0
  return `${Math.round(rate * 100)} %`
})

const otpAlert = computed(() => data.value?.alerts?.otp_delivery ?? null)

const maxJourneys = computed(() =>
  Math.max(0, ...(data.value?.steps ?? []).map((step) => step.journeys ?? 0))
)

function journeyShare(journeys) {
  if (maxJourneys.value <= 0) return '0%'
  return `${Math.min(100, Math.round(((journeys ?? 0) / maxJourneys.value) * 100))}%`
}

async function load() {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const res = await api.get('/admin/funnel/stats', { params: { days: days.value }, _skipToast: true })
    data.value = res.data?.data ?? null
  } catch (e) {
    errorMessage.value = e?.response?.data?.localized_message || e?.message || 'Erreur'
  } finally {
    isLoading.value = false
  }
}

onMounted(load)
</script>
