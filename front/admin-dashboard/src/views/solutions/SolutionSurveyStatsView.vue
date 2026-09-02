<template>
  <div class="space-y-8 animate-fade-in">
    <!-- Header -->
    <div class="card p-8 relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-64 h-64 bg-brand-500/10 rounded-full blur-3xl"></div>

      <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
          <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white mb-2">
            {{ $t('surveyStats.title') }}
          </h1>
          <p class="text-slate-500 dark:text-slate-400 font-medium">
            {{ $t('surveyStats.subtitle') }}
          </p>
        </div>

        <div class="flex items-center gap-4">
          <button @click="load" :disabled="isLoading" class="btn-secondary py-2.5">
            <ArrowPathIcon :class="['h-5 w-5 mr-2', isLoading ? 'animate-spin' : '']" />
            {{ $t('surveyStats.refresh') }}
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
      <button class="ml-3 underline font-bold" @click="load">{{ $t('surveyStats.retry') }}</button>
    </div>

    <div v-if="isLoading" class="py-12 text-center text-sm font-bold text-slate-400 uppercase tracking-widest">
      {{ $t('surveyStats.loading') }}
    </div>

    <template v-else-if="data">
      <!-- Métriques clés -->
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <MetricCard
          :title="$t('surveyStats.metricResponses')"
          :value="String(data.totals.responses ?? 0)"
          icon="ChatBubbleLeftRightIcon"
          color="blue"
        />
        <MetricCard
          :title="$t('surveyStats.metricConverted')"
          :value="String(data.totals.converted ?? 0)"
          icon="UserPlusIcon"
          color="green"
        />
        <MetricCard
          :title="$t('surveyStats.metricConversionRate')"
          :value="conversionRateLabel"
          icon="ArrowTrendingUpIcon"
          color="purple"
        />
        <MetricCard
          :title="$t('surveyStats.metricPackagesSuggested')"
          :value="String(totalPackages)"
          icon="GiftIcon"
          color="amber"
        />
      </div>

      <!-- Volume par solution -->
      <div class="card p-8 animate-slide-up" style="animation-delay: 0.1s">
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-1">{{ $t('surveyStats.bySolutionTitle') }}</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">{{ $t('surveyStats.bySolutionHint') }}</p>

        <div v-if="data.by_solution.length === 0" class="py-8 text-center">
          <InformationCircleIcon class="mx-auto h-10 w-10 text-slate-300" />
          <p class="mt-3 text-sm font-medium text-slate-500">{{ $t('surveyStats.empty') }}</p>
        </div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-widest text-slate-400">
              <th class="pb-3 font-bold">{{ $t('surveyStats.solutionColumn') }}</th>
              <th class="pb-3 font-bold">{{ $t('surveyStats.responsesColumn') }}</th>
              <th class="pb-3 font-bold">{{ $t('surveyStats.shareColumn') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200/50 dark:divide-slate-800/50">
            <tr v-for="row in data.by_solution" :key="row.solution">
              <td class="py-3 font-semibold text-slate-800 dark:text-slate-200">{{ row.solution }}</td>
              <td class="py-3 text-slate-600 dark:text-slate-300">{{ row.responses }}</td>
              <td class="py-3">
                <div class="flex items-center gap-2">
                  <div class="h-2 flex-1 max-w-[240px] rounded-full bg-slate-100 dark:bg-slate-800">
                    <div
                      class="h-2 rounded-full bg-brand-500"
                      :style="{ width: sharePercent(row.responses) }"
                    ></div>
                  </div>
                  <span class="text-xs text-slate-500">{{ sharePercent(row.responses) }}</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Packs suggérés -->
      <div class="card p-8 animate-slide-up" style="animation-delay: 0.2s">
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-1">{{ $t('surveyStats.packagesTitle') }}</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">{{ $t('surveyStats.packagesHint') }}</p>

        <div v-if="data.packages.length === 0" class="py-8 text-center">
          <InformationCircleIcon class="mx-auto h-10 w-10 text-slate-300" />
          <p class="mt-3 text-sm font-medium text-slate-500">{{ $t('surveyStats.empty') }}</p>
        </div>
        <ul v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <li
            v-for="pkg in data.packages"
            :key="pkg.key"
            class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 p-4 flex items-center justify-between"
          >
            <span class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ pkg.key }}</span>
            <span class="text-xs font-bold text-brand-600 dark:text-brand-400">{{ pkg.count }}</span>
          </li>
        </ul>
      </div>

      <!-- Distribution des réponses -->
      <div class="card p-8 animate-slide-up" style="animation-delay: 0.3s">
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-1">{{ $t('surveyStats.answersTitle') }}</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">{{ $t('surveyStats.answersHint') }}</p>

        <div v-if="data.answers.length === 0" class="py-8 text-center">
          <InformationCircleIcon class="mx-auto h-10 w-10 text-slate-300" />
          <p class="mt-3 text-sm font-medium text-slate-500">{{ $t('surveyStats.empty') }}</p>
        </div>
        <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div v-for="question in data.answers" :key="question.question" class="rounded-xl border border-slate-200 dark:border-slate-700 p-5">
            <div class="flex items-center justify-between mb-4">
              <p class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ question.question }}</p>
              <span class="text-xs font-medium text-slate-400">{{ question.total }}</span>
            </div>
            <ul class="space-y-2">
              <li v-for="value in question.values" :key="value.value" class="flex items-center gap-3">
                <span class="text-xs font-medium text-slate-500 w-24 truncate">{{ value.value }}</span>
                <div class="h-2 flex-1 rounded-full bg-slate-100 dark:bg-slate-800">
                  <div
                    class="h-2 rounded-full bg-emerald-500"
                    :style="{ width: valueShare(question.total, value.count) }"
                  ></div>
                </div>
                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ value.count }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  ArrowPathIcon,
  ArrowTrendingUpIcon,
  ChatBubbleLeftRightIcon,
  GiftIcon,
  InformationCircleIcon,
  UserPlusIcon,
} from '@heroicons/vue/24/outline'
import MetricCard from '@/components/analytics/MetricCard.vue'
import api from '@/services/api'

const data = ref(null)
const isLoading = ref(false)
const errorMessage = ref('')

const conversionRateLabel = computed(() => {
  const rate = data.value?.totals?.conversion_rate ?? 0
  return `${Math.round(rate * 100)} %`
})

const totalPackages = computed(() => {
  return (data.value?.packages ?? []).reduce((sum, pkg) => sum + (pkg.count ?? 0), 0)
})

function sharePercent(count) {
  const total = data.value?.totals?.responses ?? 0
  if (total <= 0) return '0%'
  return `${Math.round((count / total) * 100)} %`
}

function valueShare(total, count) {
  if (!total) return '0%'
  return `${Math.min(100, Math.round((count / total) * 100))}%`
}

async function load() {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const res = await api.get('/admin/solutions/survey-stats', { params: { limit: 500 }, _skipToast: true })
    data.value = res.data?.data ?? null
  } catch (e) {
    errorMessage.value = e?.response?.data?.localized_message || e?.message || 'Erreur'
  } finally {
    isLoading.value = false
  }
}

onMounted(load)
</script>
