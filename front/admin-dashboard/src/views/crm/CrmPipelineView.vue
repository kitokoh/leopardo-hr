<template>
  <div class="space-y-6 h-full flex flex-col">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between shrink-0">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Pipeline Commercial</h1>
        <p class="mt-1 text-sm text-gray-500">
          Suivi des leads de l'entrée jusqu'à la conversion payante.
        </p>
      </div>
      <button class="btn-secondary" :disabled="isLoading" @click="loadPipeline">
        Actualiser
      </button>
    </div>

    <div v-if="isLoading" class="flex-1 flex items-center justify-center p-6 text-sm text-gray-500">
      Chargement du pipeline...
    </div>
    <div v-else-if="errorMessage" class="flex-1 p-6 text-sm text-red-600 bg-red-50 rounded-lg">
      {{ errorMessage }}
    </div>
    <div v-else class="flex-1 flex gap-6 overflow-x-auto pb-4">
      
      <!-- Colonne: Leads -->
      <div class="flex-shrink-0 w-80 bg-slate-50 dark:bg-slate-800/30 rounded-xl flex flex-col border border-slate-200 dark:border-slate-800">
        <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center bg-white dark:bg-slate-900 rounded-t-xl">
          <h2 class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-yellow-400"></span>
            Leads Entrants
          </h2>
          <span class="text-xs font-semibold px-2 py-1 bg-slate-100 dark:bg-slate-800 rounded-full text-slate-600 dark:text-slate-400">
            {{ pipeline.leads?.length || 0 }}
          </span>
        </div>
        <div class="flex-1 p-3 space-y-3 overflow-y-auto">
          <div v-for="item in pipeline.leads" :key="item.id" class="bg-white dark:bg-slate-900 p-4 rounded-lg shadow-sm border border-slate-200 dark:border-slate-800 hover:border-brand-500 transition-colors cursor-pointer" @click="openRequest(item.id)">
            <h3 class="font-bold text-slate-900 dark:text-white">{{ item.company_name }}</h3>
            <p class="text-xs text-slate-500 mt-1">{{ item.sector || 'Secteur non précisé' }}</p>
            <div class="mt-3 flex items-center justify-between">
              <span class="text-xs text-slate-400">{{ formatDate(item.created_at) }}</span>
            </div>
          </div>
          <div v-if="!pipeline.leads?.length" class="text-center p-4 text-sm text-slate-400 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-lg">
            Aucun lead
          </div>
        </div>
      </div>

      <!-- Colonne: Trials -->
      <div class="flex-shrink-0 w-80 bg-emerald-50/30 dark:bg-emerald-900/10 rounded-xl flex flex-col border border-emerald-100 dark:border-emerald-900/30">
        <div class="p-4 border-b border-emerald-100 dark:border-emerald-900/30 flex justify-between items-center bg-white dark:bg-slate-900 rounded-t-xl">
          <h2 class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
            En Essai (Trial)
          </h2>
          <span class="text-xs font-semibold px-2 py-1 bg-slate-100 dark:bg-slate-800 rounded-full text-slate-600 dark:text-slate-400">
            {{ pipeline.trials?.length || 0 }}
          </span>
        </div>
        <div class="flex-1 p-3 space-y-3 overflow-y-auto">
          <div v-for="item in pipeline.trials" :key="item.id" class="bg-white dark:bg-slate-900 p-4 rounded-lg shadow-sm border border-emerald-100 dark:border-emerald-900/50 hover:border-brand-500 transition-colors cursor-pointer" @click="openCompany(item.company.id)">
            <h3 class="font-bold text-slate-900 dark:text-white">{{ item.company_name }}</h3>
            <p class="text-xs text-slate-500 mt-1">{{ item.email }}</p>
            <div class="mt-3 flex items-center justify-between">
              <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">{{ item.company.days_left }}j restants</span>
            </div>
          </div>
          <div v-if="!pipeline.trials?.length" class="text-center p-4 text-sm text-slate-400 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-lg">
            Aucun essai en cours
          </div>
        </div>
      </div>

      <!-- Colonne: Active (Payant) -->
      <div class="flex-shrink-0 w-80 bg-blue-50/30 dark:bg-blue-900/10 rounded-xl flex flex-col border border-blue-100 dark:border-blue-900/30">
        <div class="p-4 border-b border-blue-100 dark:border-blue-900/30 flex justify-between items-center bg-white dark:bg-slate-900 rounded-t-xl">
          <h2 class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-blue-500"></span>
            Clients Actifs
          </h2>
          <span class="text-xs font-semibold px-2 py-1 bg-slate-100 dark:bg-slate-800 rounded-full text-slate-600 dark:text-slate-400">
            {{ pipeline.active?.length || 0 }}
          </span>
        </div>
        <div class="flex-1 p-3 space-y-3 overflow-y-auto">
          <div v-for="item in pipeline.active" :key="item.id" class="bg-white dark:bg-slate-900 p-4 rounded-lg shadow-sm border border-blue-100 dark:border-blue-900/50 hover:border-brand-500 transition-colors cursor-pointer" @click="openCompany(item.company.id)">
            <h3 class="font-bold text-slate-900 dark:text-white">{{ item.company_name }}</h3>
            <div class="mt-3 flex items-center justify-between">
              <span class="text-xs font-medium text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">Actif</span>
            </div>
          </div>
          <div v-if="!pipeline.active?.length" class="text-center p-4 text-sm text-slate-400 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-lg">
            Aucun client actif converti
          </div>
        </div>
      </div>

      <!-- Colonne: Rejected -->
      <div class="flex-shrink-0 w-80 bg-slate-100/50 dark:bg-slate-800/50 rounded-xl flex flex-col border border-slate-200 dark:border-slate-700 opacity-75">
        <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-transparent rounded-t-xl">
          <h2 class="font-bold text-slate-600 dark:text-slate-400 flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-slate-400"></span>
            Rejetés / Expirés
          </h2>
          <span class="text-xs font-semibold px-2 py-1 bg-slate-200 dark:bg-slate-700 rounded-full text-slate-600 dark:text-slate-400">
            {{ pipeline.rejected?.length || 0 }}
          </span>
        </div>
        <div class="flex-1 p-3 space-y-3 overflow-y-auto">
          <div v-for="item in pipeline.rejected" :key="item.id" class="bg-slate-50 dark:bg-slate-800/80 p-4 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <h3 class="font-bold text-slate-600 dark:text-slate-400">{{ item.company_name }}</h3>
            <p class="text-xs text-slate-400 mt-1">
              {{ item.company ? item.company.status : item.status }}
            </p>
          </div>
          <div v-if="!pipeline.rejected?.length" class="text-center p-4 text-sm text-slate-400 border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-lg">
            Aucun historique
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'

const router = useRouter()
const isLoading = ref(false)
const errorMessage = ref('')
const pipeline = ref({
  leads: [],
  trials: [],
  active: [],
  rejected: []
})

async function loadPipeline() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await api.get('/platform/crm/pipeline')
    pipeline.value = response.data?.data || { leads: [], trials: [], active: [], rejected: [] }
  } catch (error) {
    console.error('Failed to load CRM pipeline:', error)
    errorMessage.value = 'Impossible de charger le pipeline CRM.'
  } finally {
    isLoading.value = false
  }
}

function openRequest(id) {
  router.push('/support')
}

function openCompany(companyId) {
  router.push(`/companies/${companyId}`)
}

function formatDate(value) {
  if (!value) return ''
  return new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(value))
}

onMounted(loadPipeline)
</script>
