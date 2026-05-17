<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-5">
      <MetricCard title="Effectif total" :value="metrics.headcount" />
      <MetricCard title="Taux absenteisme" :value="metrics.absenteeism_rate" format="percent" />
      <MetricCard title="Taux turnover" :value="metrics.turnover_rate" format="percent" />
      <MetricCard title="Heures supp." :value="metrics.overtime_hours" />
      <MetricCard title="Masse salariale" :value="metrics.payroll_total" format="currency" />
    </div>

    <div class="flex gap-2">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        :class="[
          'rounded-md px-4 py-2 text-sm font-medium',
          activeTab === tab.key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50'
        ]"
        @click="loadReport(tab.key)"
      >
        {{ tab.label }}
      </button>
    </div>

    <div v-if="loading" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
      Chargement du rapport...
    </div>
    <div v-else-if="error" class="rounded-lg bg-white p-8 text-center text-sm text-red-600 shadow">
      {{ error }}
    </div>
    <div v-else-if="reportData" class="rounded-lg bg-white p-6 shadow">
      <h2 class="mb-4 text-lg font-semibold text-gray-900">
        {{ currentTabLabel }}
      </h2>

      <div v-if="activeTab === 'headcount'" class="space-y-4">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <div class="rounded-md border p-3">
            <p class="text-sm text-gray-500">Total</p>
            <p class="text-xl font-bold">{{ reportData.total ?? 0 }}</p>
          </div>
          <div v-for="(count, dept) in (reportData.by_department || {})" :key="dept" class="rounded-md border p-3">
            <p class="text-sm text-gray-500">{{ dept }}</p>
            <p class="text-xl font-bold">{{ count }}</p>
          </div>
        </div>
      </div>

      <div v-else-if="activeTab === 'absenteeism'" class="space-y-4">
        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <div class="rounded-md border p-3">
            <dt class="text-sm text-gray-500">Taux</dt>
            <dd class="text-xl font-bold">{{ reportData.rate ?? 0 }}%</dd>
          </div>
          <div class="rounded-md border p-3">
            <dt class="text-sm text-gray-500">Jours total</dt>
            <dd class="text-xl font-bold">{{ reportData.total_days ?? 0 }}</dd>
          </div>
          <div class="rounded-md border p-3">
            <dt class="text-sm text-gray-500">Duree moyenne</dt>
            <dd class="text-xl font-bold">{{ reportData.avg_duration ?? 0 }}j</dd>
          </div>
        </dl>
      </div>

      <div v-else-if="activeTab === 'turnover'" class="space-y-4">
        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
          <div class="rounded-md border p-3">
            <dt class="text-sm text-gray-500">Taux turnover</dt>
            <dd class="text-xl font-bold">{{ reportData.rate ?? 0 }}%</dd>
          </div>
          <div class="rounded-md border p-3">
            <dt class="text-sm text-gray-500">Departs</dt>
            <dd class="text-xl font-bold">{{ reportData.departures ?? 0 }}</dd>
          </div>
          <div class="rounded-md border p-3">
            <dt class="text-sm text-gray-500">Arrivees</dt>
            <dd class="text-xl font-bold">{{ reportData.arrivals ?? 0 }}</dd>
          </div>
        </dl>
      </div>

      <div v-else class="text-sm text-gray-600">
        <pre class="overflow-x-auto rounded bg-gray-50 p-4">{{ JSON.stringify(reportData, null, 2) }}</pre>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'
import MetricCard from '@/components/common/MetricCard.vue'

const loading = ref(false)
const error = ref('')
const activeTab = ref('headcount')
const reportData = ref(null)
const metrics = ref({
  headcount: 0,
  absenteeism_rate: 0,
  turnover_rate: 0,
  overtime_hours: 0,
  payroll_total: 0,
})

const tabs = [
  { key: 'headcount', label: 'Effectifs', endpoint: '/v1/reports/headcount' },
  { key: 'absenteeism', label: 'Absenteisme', endpoint: '/v1/reports/absenteeism' },
  { key: 'turnover', label: 'Turnover', endpoint: '/v1/reports/turnover' },
  { key: 'overtime', label: 'Heures supp.', endpoint: '/v1/reports/overtime' },
  { key: 'payroll-summary', label: 'Masse salariale', endpoint: '/v1/reports/payroll-summary' },
]

const currentTabLabel = computed(() => tabs.find(t => t.key === activeTab.value)?.label ?? '')

async function loadReport(key) {
  activeTab.value = key
  const tab = tabs.find(t => t.key === key)
  if (!tab) return

  loading.value = true
  error.value = ''
  try {
    const res = await api.get(tab.endpoint)
    reportData.value = res.data.data ?? res.data
  } catch (e) {
    error.value = 'Impossible de charger le rapport.'
    console.warn('ReportsView fetch failed', e)
  } finally {
    loading.value = false
  }
}

async function loadMetrics() {
  try {
    const [hc, ab, to] = await Promise.allSettled([
      api.get('/v1/reports/headcount'),
      api.get('/v1/reports/absenteeism'),
      api.get('/v1/reports/turnover'),
    ])

    if (hc.status === 'fulfilled') {
      metrics.value.headcount = hc.value.data?.data?.total ?? 0
    }
    if (ab.status === 'fulfilled') {
      metrics.value.absenteeism_rate = ab.value.data?.data?.rate ?? 0
    }
    if (to.status === 'fulfilled') {
      metrics.value.turnover_rate = to.value.data?.data?.rate ?? 0
    }
  } catch (e) {
    console.warn('Metrics load failed', e)
  }
}

onMounted(() => {
  loadMetrics()
  loadReport('headcount')
})
</script>
