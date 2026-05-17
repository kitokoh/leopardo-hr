<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Rapports RH</h1>
      <div class="flex gap-2">
        <select v-model="selectedPeriod" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
          <option value="month">Ce mois</option>
          <option value="quarter">Ce trimestre</option>
          <option value="year">Cette annee</option>
        </select>
        <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700" @click="exportAll">
          Exporter PDF
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard title="Effectif total" :value="headcount.total" icon="UsersIcon" color="blue" />
      <StatsCard title="Taux absenteisme" :value="absenteeism.rate + '%'" icon="ChartBarIcon" color="yellow" />
      <StatsCard title="Turnover" :value="turnover.rate + '%'" icon="ChartBarIcon" color="red" />
      <StatsCard title="Masse salariale" :value="formatCurrency(payrollSummary.total_gross)" icon="ChartBarIcon" color="green" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <div class="rounded-lg bg-white p-6 shadow ring-1 ring-gray-200">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Effectif par departement</h3>
        <div v-if="loadingHeadcount" class="py-8 text-center text-sm text-gray-400">Chargement...</div>
        <div v-else class="space-y-3">
          <div v-for="dept in headcount.by_department" :key="dept.department" class="flex items-center gap-3">
            <span class="w-32 truncate text-sm text-gray-600">{{ dept.department }}</span>
            <div class="flex-1">
              <div class="h-4 rounded-full bg-gray-100">
                <div
                  class="h-4 rounded-full bg-indigo-500"
                  :style="{ width: Math.min((dept.count / Math.max(headcount.total, 1)) * 100, 100) + '%' }"
                />
              </div>
            </div>
            <span class="w-10 text-right text-sm font-medium text-gray-900">{{ dept.count }}</span>
          </div>
          <p v-if="headcount.by_department.length === 0" class="text-center text-sm text-gray-400">Aucune donnee.</p>
        </div>
      </div>

      <div class="rounded-lg bg-white p-6 shadow ring-1 ring-gray-200">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Effectif par statut</h3>
        <div v-if="loadingHeadcount" class="py-8 text-center text-sm text-gray-400">Chargement...</div>
        <div v-else class="space-y-3">
          <div v-for="st in headcount.by_status" :key="st.status" class="flex items-center gap-3">
            <StatusBadge :status="st.status" class="w-24" />
            <div class="flex-1">
              <div class="h-4 rounded-full bg-gray-100">
                <div
                  class="h-4 rounded-full"
                  :class="statusColor(st.status)"
                  :style="{ width: Math.min((st.count / Math.max(headcount.total, 1)) * 100, 100) + '%' }"
                />
              </div>
            </div>
            <span class="w-10 text-right text-sm font-medium text-gray-900">{{ st.count }}</span>
          </div>
          <p v-if="headcount.by_status.length === 0" class="text-center text-sm text-gray-400">Aucune donnee.</p>
        </div>
      </div>

      <div class="rounded-lg bg-white p-6 shadow ring-1 ring-gray-200">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Absenteisme</h3>
        <div v-if="loadingAbsenteeism" class="py-8 text-center text-sm text-gray-400">Chargement...</div>
        <div v-else>
          <div class="mb-4 grid grid-cols-3 gap-4 text-center">
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ absenteeism.total_days }}</p>
              <p class="text-xs text-gray-500">Jours d'absence</p>
            </div>
            <div>
              <p class="text-2xl font-bold text-yellow-600">{{ absenteeism.rate }}%</p>
              <p class="text-xs text-gray-500">Taux</p>
            </div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ absenteeism.avg_duration }}</p>
              <p class="text-xs text-gray-500">Duree moyenne (j)</p>
            </div>
          </div>
          <div class="space-y-2">
            <div v-for="type in absenteeism.by_type" :key="type.type" class="flex justify-between text-sm">
              <span class="text-gray-600">{{ type.type }}</span>
              <span class="font-medium text-gray-900">{{ type.count }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-lg bg-white p-6 shadow ring-1 ring-gray-200">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Heures supplementaires</h3>
        <div v-if="loadingOvertime" class="py-8 text-center text-sm text-gray-400">Chargement...</div>
        <div v-else>
          <div class="mb-4 grid grid-cols-2 gap-4 text-center">
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ overtime.total_hours }}</p>
              <p class="text-xs text-gray-500">Heures totales</p>
            </div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ overtime.employee_count }}</p>
              <p class="text-xs text-gray-500">Employes concernes</p>
            </div>
          </div>
          <div class="space-y-2">
            <div v-for="dept in overtime.by_department" :key="dept.department" class="flex justify-between text-sm">
              <span class="text-gray-600">{{ dept.department }}</span>
              <span class="font-medium text-gray-900">{{ dept.hours }}h</span>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-lg bg-white p-6 shadow ring-1 ring-gray-200 lg:col-span-2">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Resume Masse Salariale</h3>
        <div v-if="loadingPayroll" class="py-8 text-center text-sm text-gray-400">Chargement...</div>
        <div v-else>
          <div class="mb-4 grid grid-cols-2 gap-4 text-center sm:grid-cols-4">
            <div>
              <p class="text-xl font-bold text-gray-900">{{ formatCurrency(payrollSummary.total_gross) }}</p>
              <p class="text-xs text-gray-500">Brut total</p>
            </div>
            <div>
              <p class="text-xl font-bold text-gray-900">{{ formatCurrency(payrollSummary.total_net) }}</p>
              <p class="text-xs text-gray-500">Net total</p>
            </div>
            <div>
              <p class="text-xl font-bold text-gray-900">{{ formatCurrency(payrollSummary.total_employer_charges) }}</p>
              <p class="text-xs text-gray-500">Charges patronales</p>
            </div>
            <div>
              <p class="text-xl font-bold text-gray-900">{{ payrollSummary.employee_count }}</p>
              <p class="text-xs text-gray-500">Bulletins</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="rounded-lg bg-white p-6 shadow ring-1 ring-gray-200">
      <h3 class="mb-4 text-sm font-semibold text-gray-900">Rapports avances</h3>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <button
          v-for="report in advancedReports"
          :key="report.key"
          class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 text-left transition hover:border-indigo-300 hover:bg-indigo-50"
          @click="fetchAdvancedReport(report.key)"
        >
          <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100">
            <span class="text-lg">{{ report.icon }}</span>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-900">{{ report.label }}</p>
            <p class="text-xs text-gray-500">{{ report.description }}</p>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'

const selectedPeriod = ref('month')

const loadingHeadcount = ref(false)
const loadingAbsenteeism = ref(false)
const loadingOvertime = ref(false)
const loadingPayroll = ref(false)

const headcount = ref({ total: 0, by_department: [], by_status: [] })
const absenteeism = ref({ total_days: 0, rate: 0, avg_duration: 0, by_type: [] })
const turnover = ref({ rate: 0 })
const overtime = ref({ total_hours: 0, employee_count: 0, by_department: [] })
const payrollSummary = ref({ total_gross: 0, total_net: 0, total_employer_charges: 0, employee_count: 0 })

const advancedReports = [
  { key: 'recruitment-pipeline', label: 'Pipeline recrutement', description: 'Candidatures par etape', icon: '👥' },
  { key: 'training-completion', label: 'Formations', description: 'Taux de completion', icon: '📚' },
  { key: 'demographics', label: 'Demographie', description: 'Repartition age, genre, anciennete', icon: '📊' },
  { key: 'cost-analysis', label: 'Analyse couts', description: 'Couts RH par departement', icon: '💰' },
  { key: 'loan-summary', label: 'Prets employes', description: 'Encours et remboursements', icon: '🏦' },
]

function statusColor(status) {
  const colors = {
    active: 'bg-green-500',
    inactive: 'bg-gray-400',
    on_leave: 'bg-yellow-500',
    suspended: 'bg-red-500',
    terminated: 'bg-red-700',
  }
  return colors[status] || 'bg-gray-300'
}

function formatCurrency(value) {
  if (!value && value !== 0) return '-'
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(value)
}

function periodParams() {
  const now = new Date()
  const year = now.getFullYear()
  const month = now.getMonth()

  if (selectedPeriod.value === 'month') {
    return { start: `${year}-${String(month + 1).padStart(2, '0')}-01`, end: new Date(year, month + 1, 0).toISOString().split('T')[0] }
  }
  if (selectedPeriod.value === 'quarter') {
    const qStart = Math.floor(month / 3) * 3
    return { start: `${year}-${String(qStart + 1).padStart(2, '0')}-01`, end: new Date(year, qStart + 3, 0).toISOString().split('T')[0] }
  }
  return { start: `${year}-01-01`, end: `${year}-12-31` }
}

async function fetchHeadcount() {
  loadingHeadcount.value = true
  try {
    const res = await api.get('/v1/reports/headcount')
    headcount.value = res.data.data || res.data || headcount.value
  } catch (e) {
    console.warn('Headcount fetch failed', e)
  } finally {
    loadingHeadcount.value = false
  }
}

async function fetchAbsenteeism() {
  loadingAbsenteeism.value = true
  try {
    const params = periodParams()
    const res = await api.get('/v1/reports/absenteeism', { params })
    absenteeism.value = res.data.data || res.data || absenteeism.value
  } catch (e) {
    console.warn('Absenteeism fetch failed', e)
  } finally {
    loadingAbsenteeism.value = false
  }
}

async function fetchTurnover() {
  try {
    const params = periodParams()
    const res = await api.get('/v1/reports/turnover', { params })
    turnover.value = res.data.data || res.data || turnover.value
  } catch (e) {
    console.warn('Turnover fetch failed', e)
  }
}

async function fetchOvertime() {
  loadingOvertime.value = true
  try {
    const params = periodParams()
    const res = await api.get('/v1/reports/overtime', { params })
    overtime.value = res.data.data || res.data || overtime.value
  } catch (e) {
    console.warn('Overtime fetch failed', e)
  } finally {
    loadingOvertime.value = false
  }
}

async function fetchPayrollSummary() {
  loadingPayroll.value = true
  try {
    const params = periodParams()
    const res = await api.get('/v1/reports/payroll-summary', { params })
    payrollSummary.value = res.data.data || res.data || payrollSummary.value
  } catch (e) {
    console.warn('Payroll summary fetch failed', e)
  } finally {
    loadingPayroll.value = false
  }
}

async function fetchAdvancedReport(key) {
  try {
    const params = periodParams()
    const res = await api.get(`/v1/reports/${key}`, { params })
    window.alert(`Rapport "${key}" : ${JSON.stringify(res.data.data || res.data, null, 2).slice(0, 500)}`)
  } catch (e) {
    console.warn(`Advanced report ${key} failed`, e)
  }
}

function exportAll() {
  window.open('/api/v1/export/reports?format=pdf', '_blank')
}

async function fetchAll() {
  await Promise.all([fetchHeadcount(), fetchAbsenteeism(), fetchTurnover(), fetchOvertime(), fetchPayrollSummary()])
}

watch(selectedPeriod, fetchAll)
onMounted(fetchAll)
</script>
