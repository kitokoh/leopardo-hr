<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Dashboard Predictif IA</h1>
        <p class="mt-1 text-sm text-gray-500">Predictions et notifications proactives.</p>
      </div>
      <button
        class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        @click="refreshAll"
      >
        Actualiser
      </button>
    </div>

    <!-- Proactive Notifications -->
    <div v-if="notifications.length > 0" class="space-y-2">
      <h2 class="text-lg font-semibold text-gray-900">Notifications proactives</h2>
      <div
        v-for="(notif, i) in notifications"
        :key="i"
        :class="[
          'flex items-start gap-3 rounded-lg border p-4',
          notif.severity === 'critical' ? 'border-red-200 bg-red-50' :
          notif.severity === 'warning' ? 'border-yellow-200 bg-yellow-50' :
          'border-blue-200 bg-blue-50'
        ]"
      >
        <span
          :class="[
            'mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white',
            notif.severity === 'critical' ? 'bg-red-500' :
            notif.severity === 'warning' ? 'bg-yellow-500' :
            'bg-blue-500'
          ]"
        >!</span>
        <div class="flex-1">
          <p class="text-sm font-semibold text-gray-900">{{ notif.title }}</p>
          <p class="mt-0.5 text-sm text-gray-600">{{ notif.message }}</p>
        </div>
        <a
          v-if="notif.action_url"
          :href="notif.action_url"
          class="shrink-0 text-sm font-medium text-indigo-600 hover:text-indigo-800"
        >Voir</a>
      </div>
    </div>
    <div v-else-if="!loading" class="rounded-lg bg-green-50 p-4 text-center text-sm text-green-700">
      Aucune notification proactive — tout est en ordre.
    </div>

    <!-- Turnover Prediction -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <div class="rounded-lg bg-white p-6 shadow">
        <h2 class="mb-4 text-lg font-semibold text-gray-900">Prediction turnover</h2>
        <div v-if="loadingTurnover" class="py-8 text-center text-sm text-gray-500">Analyse en cours...</div>
        <template v-else-if="turnover">
          <div class="mb-4 grid grid-cols-2 gap-4">
            <div class="rounded-md bg-orange-50 p-3 text-center">
              <p class="text-2xl font-bold text-orange-700">{{ turnover.overall_turnover_rate }}%</p>
              <p class="text-xs text-orange-500">Taux turnover 12 mois</p>
            </div>
            <div class="rounded-md bg-red-50 p-3 text-center">
              <p class="text-2xl font-bold text-red-700">{{ turnover.high_risk_employees.length }}</p>
              <p class="text-xs text-red-500">Employes a risque</p>
            </div>
          </div>
          <div v-if="turnover.department_risks.length > 0">
            <h3 class="mb-2 text-sm font-medium text-gray-700">Risque par departement</h3>
            <div class="space-y-2">
              <div v-for="dept in turnover.department_risks.slice(0, 5)" :key="dept.department" class="flex items-center gap-3">
                <span class="w-32 truncate text-xs text-gray-600">{{ dept.department }}</span>
                <div class="flex-1">
                  <div class="h-2 rounded-full bg-gray-200">
                    <div
                      class="h-2 rounded-full"
                      :class="dept.risk > 20 ? 'bg-red-500' : dept.risk > 10 ? 'bg-yellow-500' : 'bg-green-500'"
                      :style="{ width: Math.min(dept.risk, 100) + '%' }"
                    />
                  </div>
                </div>
                <span class="w-12 text-right text-xs font-medium text-gray-700">{{ dept.risk }}%</span>
              </div>
            </div>
          </div>
          <div v-if="turnover.high_risk_employees.length > 0" class="mt-4">
            <h3 class="mb-2 text-sm font-medium text-gray-700">Employes a risque eleve</h3>
            <div class="space-y-1">
              <div v-for="emp in turnover.high_risk_employees.slice(0, 5)" :key="emp.employee_id" class="flex items-center justify-between rounded bg-gray-50 px-3 py-2">
                <span class="text-sm text-gray-900">{{ emp.name }}</span>
                <div class="flex items-center gap-2">
                  <span v-for="factor in emp.factors.slice(0, 2)" :key="factor" class="rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-700">{{ factor }}</span>
                  <span class="text-sm font-bold text-red-600">{{ emp.risk }}%</span>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- Absenteeism Prediction -->
      <div class="rounded-lg bg-white p-6 shadow">
        <h2 class="mb-4 text-lg font-semibold text-gray-900">Prediction absenteisme</h2>
        <div v-if="loadingAbsenteeism" class="py-8 text-center text-sm text-gray-500">Analyse en cours...</div>
        <template v-else-if="absenteeism">
          <div class="mb-4 rounded-md bg-blue-50 p-3 text-center">
            <p class="text-2xl font-bold text-blue-700">{{ absenteeism.predicted_days_next_month }}</p>
            <p class="text-xs text-blue-500">Jours prevus le mois prochain</p>
          </div>
          <div v-if="absenteeism.high_risk_periods.length > 0">
            <h3 class="mb-2 text-sm font-medium text-gray-700">Periodes a risque</h3>
            <div class="space-y-2">
              <div v-for="period in absenteeism.high_risk_periods" :key="period.month" class="flex items-center justify-between rounded bg-gray-50 px-3 py-2">
                <span class="text-sm text-gray-600">{{ period.month }}</span>
                <span class="text-sm font-medium text-gray-900">{{ period.predicted_rate }} jours</span>
              </div>
            </div>
          </div>
          <div v-if="absenteeism.department_predictions.length > 0" class="mt-4">
            <h3 class="mb-2 text-sm font-medium text-gray-700">Prediction par departement</h3>
            <div class="space-y-2">
              <div v-for="dept in absenteeism.department_predictions.slice(0, 5)" :key="dept.department" class="flex items-center justify-between rounded bg-gray-50 px-3 py-2">
                <span class="text-sm text-gray-600">{{ dept.department }}</span>
                <div class="text-right">
                  <span class="text-sm font-medium text-gray-900">{{ dept.predicted_days }}j</span>
                  <span class="ml-1 text-xs text-gray-400">(moy: {{ dept.historical_avg }}j)</span>
                </div>
              </div>
            </div>
          </div>
          <div v-if="absenteeism.recommendations.length > 0" class="mt-4 rounded-md bg-amber-50 p-3">
            <h3 class="mb-1 text-sm font-medium text-amber-800">Recommandations</h3>
            <ul class="space-y-1">
              <li v-for="rec in absenteeism.recommendations" :key="rec" class="text-sm text-amber-700">{{ rec }}</li>
            </ul>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const loading = ref(false)
const loadingTurnover = ref(false)
const loadingAbsenteeism = ref(false)
const notifications = ref([])
const turnover = ref(null)
const absenteeism = ref(null)

async function fetchNotifications() {
  loading.value = true
  try {
    const res = await api.get('/v1/predictions/notifications')
    notifications.value = res.data.data || []
  } catch {
    notifications.value = []
  } finally {
    loading.value = false
  }
}

async function fetchTurnover() {
  loadingTurnover.value = true
  try {
    const res = await api.get('/v1/predictions/turnover')
    turnover.value = res.data.data || null
  } catch {
    turnover.value = null
  } finally {
    loadingTurnover.value = false
  }
}

async function fetchAbsenteeism() {
  loadingAbsenteeism.value = true
  try {
    const res = await api.get('/v1/predictions/absenteeism')
    absenteeism.value = res.data.data || null
  } catch {
    absenteeism.value = null
  } finally {
    loadingAbsenteeism.value = false
  }
}

function refreshAll() {
  fetchNotifications()
  fetchTurnover()
  fetchAbsenteeism()
}

onMounted(refreshAll)
</script>
