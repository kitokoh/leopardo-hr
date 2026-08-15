<template>
  <div class="space-y-8 animate-fade-in">
    <!-- Header with actions -->
    <div class="card p-8 relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-64 h-64 bg-brand-500/10 rounded-full blur-3xl"></div>

      <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
          <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white mb-2">{{ $t('users.page.title', 'Gestion des Utilisateurs') }}</h1>
          <p class="text-slate-500 dark:text-slate-400 font-medium">
            {{ usersSummary }}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button
            @click="showBulkActions = !showBulkActions"
            :class="[
              'inline-flex items-center px-4 py-2.5 border text-xs font-black uppercase tracking-widest rounded-xl transition-all duration-300 shadow-glass-sm',
              selectedUsers.length > 0
                ? 'border-brand-300 text-brand-700 bg-brand-50 hover:bg-brand-100 dark:bg-brand-900/30 dark:text-brand-300 dark:border-brand-800'
                : 'border-slate-200 text-slate-700 hover:glass-card dark:border-slate-800 dark:text-slate-300 dark:bg-slate-900/50'
            ]"
          >
            <CheckCircleIcon class="h-4 w-4 mr-2" />
            {{ $t('users.actions.bulk', 'Actions').replace(':count', String(selectedUsers.length)) }}
          </button>

          <button
            @click="exportUsers"
            class="btn-secondary py-2.5 text-xs font-black uppercase tracking-widest"
          >
            <DocumentArrowDownIcon class="h-4 w-4 mr-2" />
            {{ $t('users.actions.export', 'Exporter') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Filters and Search -->
    <div class="card p-8 animate-slide-up" style="animation-delay: 0.1s">
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Search -->
        <div class="lg:col-span-2">
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2 ml-1">{{ $t('users.filters.search.label', 'Rechercher') }}</label>
          <div class="relative group">
            <MagnifyingGlassIcon class="absolute left-4 top-1/2 transform -translate-y-1/2 h-5 w-5 text-slate-400 group-focus-within:text-brand-500 transition-colors" />
            <input
              v-model="searchQuery"
              type="text"
              :placeholder="$t('users.filters.search.placeholder', 'Nom, email, entreprise...')"
              class="block w-full pl-12 pr-4 py-3 bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-sm font-bold focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all outline-none"
            />
          </div>
        </div>

        <!-- Status Filter -->
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2 ml-1">{{ $t('users.filters.status.label', 'Statut') }}</label>
          <select
            v-model="filters.status"
            class="block w-full px-4 py-3 bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-sm font-bold focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all outline-none appearance-none"
          >
            <option value="">{{ $t('users.filters.status.all', 'Tous les statuts') }}</option>
            <option value="active">{{ $t('users.filters.status.active', 'Actif') }}</option>
            <option value="inactive">{{ $t('users.filters.status.inactive', 'Inactif') }}</option>
            <option value="suspended">{{ $t('users.filters.status.suspended', 'Suspendu') }}</option>
            <option value="pending">{{ $t('users.filters.status.pending', 'En attente') }}</option>
          </select>
        </div>

        <!-- Company Filter -->
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2 ml-1">{{ $t('users.filters.company.label', 'Entreprise') }}</label>
          <select
            v-model="filters.company"
            class="block w-full px-4 py-3 bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-sm font-bold focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all outline-none appearance-none"
          >
            <option value="">{{ $t('users.filters.company.all', 'Toutes les entreprises') }}</option>
            <option v-for="company in companies" :key="company.id" :value="company.id">
              {{ company.name }}
            </option>
          </select>
        </div>
      </div>

      <!-- Advanced Filters Toggle -->
      <div class="mt-6">
        <button
          @click="showAdvancedFilters = !showAdvancedFilters"
          class="text-xs font-black uppercase tracking-widest text-brand-600 hover:text-brand-700 dark:text-brand-400 transition-colors flex items-center"
        >
          <span>{{ showAdvancedFilters ? $t('users.filters.advanced.hide', 'Masquer les filtres avancés') : $t('users.filters.advanced.show', 'Afficher les filtres avancés') }}</span>
          <ChevronDownIcon :class="['ml-2 h-4 w-4 transition-transform duration-300', showAdvancedFilters ? 'rotate-180' : '']" />
        </button>
      </div>

      <!-- Advanced Filters -->
      <div v-if="showAdvancedFilters" class="mt-4 pt-4 border-t border-gray-200">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('users.filters.registrationDate.label', "Date d'inscription") }}</label>
            <select
              v-model="filters.registrationDate"
              class="block w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
            >
              <option value="">{{ $t('users.filters.registrationDate.all', 'Toutes les dates') }}</option>
              <option value="today">{{ $t('users.filters.registrationDate.today', "Aujourd'hui") }}</option>
              <option value="week">{{ $t('users.filters.registrationDate.week', 'Cette semaine') }}</option>
              <option value="month">{{ $t('users.filters.registrationDate.month', 'Ce mois') }}</option>
              <option value="quarter">{{ $t('users.filters.registrationDate.quarter', 'Ce trimestre') }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Bulk Actions Panel -->
    <div v-if="showBulkActions && selectedUsers.length > 0" class="bg-brand-50/50 dark:bg-brand-900/20 border border-brand-200 dark:border-brand-800 rounded-2xl p-4 backdrop-blur-md animate-fade-in">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <InformationCircleIcon class="h-5 w-5 text-brand-500 mr-2" />
          <span class="text-xs font-black uppercase tracking-widest text-brand-700 dark:text-brand-300">
            {{ $t('users.bulkPanel.selectedCount', ':count sélectionné(s)').replace(':count', String(selectedUsers.length)) }}
          </span>
        </div>

        <div class="flex items-center space-x-6">
          <button
            @click="bulkAction('export')"
            class="text-[10px] font-black uppercase tracking-widest text-brand-600 hover:text-brand-500 transition-colors"
          >
            {{ $t('users.bulkPanel.export', 'Exporter') }}
          </button>
          <button
            @click="clearSelection"
            class="text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-slate-500 transition-colors"
          >
            {{ $t('users.bulkPanel.cancel', 'Annuler') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Users Table -->
    <div class="card overflow-hidden animate-slide-up" style="animation-delay: 0.2s">
      <UserTable
        :users="paginatedUsers"
        :selected-users="selectedUsers"
        :loading="isLoading"
        @select="handleUserSelect"
        @select-all="handleSelectAll"
        @view="viewUser"
        @impersonate="impersonateUser"
      />

      <!-- Pagination -->
      <div class="px-6 py-5 border-t border-slate-200/50 dark:border-slate-800/50">
        <Pagination
          :current-page="currentPage"
          :total-pages="totalPages"
          :total-items="filteredUsers.length"
          :per-page="perPage"
          @page-change="currentPage = $event"
          @per-page-change="perPage = $event"
        />
      </div>
    </div>

    <!-- Modals -->
    <UserDetailModal
      v-if="showDetailModal"
      :user="selectedUser"
      @close="showDetailModal = false"
      @impersonate="impersonateUser"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import {
  DocumentArrowDownIcon,
  CheckCircleIcon,
  MagnifyingGlassIcon,
  InformationCircleIcon,
  ChevronDownIcon
} from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import api from '@/services/api.js'

// Components
import UserTable from '@/components/users/UserTable.vue'
import Pagination from '@/components/common/Pagination.vue'
import UserDetailModal from '@/components/users/UserDetailModal.vue'

const toast = useToast()
const localeStore = useLocaleStore()

function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}

// Reactive state
const searchQuery = ref('')
const selectedUsers = ref([])
const selectedUser = ref(null)
const currentPage = ref(1)
const perPage = ref(25)
const isLoading = ref(false)
const showAdvancedFilters = ref(false)
const showBulkActions = ref(false)
const showDetailModal = ref(false)

// Filters
const filters = reactive({
  status: '',
  company: '',
  registrationDate: ''
})

// Donnees reelles
const users = ref([])
const companies = ref([])
const stats = reactive({
  totalUsers: 0,
  activeUsers: 0,
  newToday: 0
})

onMounted(async () => {
  await Promise.all([loadUsers(), loadCompanies(), loadStats()])
})

// Computed properties
const filteredUsers = computed(() => {
  let filtered = users.value

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(user =>
      String(user.name || '').toLowerCase().includes(query) ||
      String(user.email || '').toLowerCase().includes(query) ||
      String(user.company_name || '').toLowerCase().includes(query)
    )
  }

  if (filters.status) {
    filtered = filtered.filter(user => user.status === filters.status)
  }

  if (filters.company) {
    filtered = filtered.filter(user => user.company_id === filters.company)
  }

  if (filters.registrationDate) {
    const now = new Date()
    filtered = filtered.filter(user => {
      const userDate = new Date(user.created_at || 0)
      switch (filters.registrationDate) {
        case 'today':
          return userDate.toDateString() === now.toDateString()
        case 'week':
          return (now - userDate) <= 7 * 24 * 60 * 60 * 1000
        case 'month':
          return (now - userDate) <= 30 * 24 * 60 * 60 * 1000
        case 'quarter':
          return (now - userDate) <= 90 * 24 * 60 * 60 * 1000
        default:
          return true
      }
    })
  }

  return filtered
})

const totalPages = computed(() => {
  return Math.max(1, Math.ceil(filteredUsers.value.length / perPage.value))
})

const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredUsers.value.slice(start, start + perPage.value)
})

const usersSummary = computed(() => {
  return t('users.page.summary', ":count utilisateur(s) - :active actif(s) - :newToday nouveau(x) aujourd'hui")
    .replace(':count', String(stats.totalUsers))
    .replace(':active', String(stats.activeUsers))
    .replace(':newToday', String(stats.newToday))
})

watch([searchQuery, filters], () => {
  currentPage.value = 1
}, { deep: true })

// Methods — donnees reelles (vague QA 2026-08-14, issue #2184)
async function loadUsers() {
  isLoading.value = true
  try {
    const res = await api.get('/admin/users', { params: { per_page: 200 } })
    users.value = res.data.data || []
  } catch (error) {
    console.error('Failed to load users:', error)
    toast.error(t('users.toast.loadError', 'Erreur lors du chargement des utilisateurs'))
  } finally {
    isLoading.value = false
  }
}

async function loadCompanies() {
  try {
    const res = await api.get('/platform/companies', { params: { per_page: 200 } })
    companies.value = res.data.data || []
  } catch (error) {
    console.error('Failed to load companies:', error)
    companies.value = []
  }
}

async function loadStats() {
  try {
    const res = await api.get('/admin/dashboard/stats')
    const s = res.data || {}
    stats.totalUsers = Number(s.totalUsers || 0)
    stats.activeUsers = users.value.filter(u => u.status === 'active').length
    stats.newToday = Number(s.newUsersToday || 0)
  } catch (error) {
    console.error('Failed to load stats:', error)
  }
}

function handleUserSelect(userId, selected) {
  if (selected) {
    selectedUsers.value.push(userId)
  } else {
    selectedUsers.value = selectedUsers.value.filter(id => id !== userId)
  }
}

function handleSelectAll(selected) {
  selectedUsers.value = selected ? filteredUsers.value.map(u => u.id) : []
}

function clearSelection() {
  selectedUsers.value = []
  showBulkActions.value = false
}

async function bulkAction(action) {
  if (action === 'export') {
    exportUsers()
  }
}

function viewUser(user) {
  selectedUser.value = user
  showDetailModal.value = true
}

async function impersonateUser(user) {
  if (!user.company_id || !user.id) {
    toast.error(t('users.toast.impersonateMissing', 'Impossible : données entreprise/employé absentes pour cet utilisateur'))
    return
  }
  const reason = window.prompt(t('users.toast.impersonateReason', 'Raison de la connexion en tant que :name (obligatoire, min. 5 caractères)').replace(':name', user.name))
  if (!reason || reason.trim().length < 5) {
    toast.warning(t('users.toast.impersonateReasonRequired', 'Raison obligatoire (min. 5 caractères) — impersonation annulée'))
    return
  }
  try {
    const res = await api.post('/platform/impersonations', {
      company_id: user.company_id,
      employee_id: user.id,
      reason: reason.trim(),
      ttl_minutes: 30
    })
    const token = res.data?.token
    if (token) {
      toast.success(t('users.toast.impersonating', 'Connexion en tant que :name — token copié dans le presse-papier').replace(':name', user.name))
      try {
        await navigator.clipboard.writeText(token)
      } catch (e) {
        console.warn('Clipboard unavailable', e)
      }
    } else {
      toast.success(t('users.toast.impersonating', 'Session d\'impersonation :name démarrée').replace(':name', user.name))
    }
    showDetailModal.value = false
  } catch (error) {
    console.error('Impersonation failed:', error)
    toast.error(error?.response?.data?.message || t('users.toast.impersonateError', "Erreur lors de l'impersonation"))
  }
}

async function exportUsers() {
  try {
    const data = filteredUsers.value
    if (data.length === 0) {
      toast.info(t('users.toast.exportEmpty', 'Aucun utilisateur à exporter'))
      return
    }
    const csvContent = "data:text/csv;charset=utf-8," +
      "Nom,Email,Statut,Entreprise,Date d'inscription\n" +
      data.map(user =>
        `${user.name || ''},${user.email || ''},${user.status || ''},${user.company_name || ''},${user.created_at ? new Date(user.created_at).toLocaleDateString() : ''}`
      ).join('\n')

    const encodedUri = encodeURI(csvContent)
    const link = document.createElement("a")
    link.setAttribute("href", encodedUri)
    link.setAttribute("download", "users-export.csv")
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)

    toast.success(t('users.toast.exportDone', 'Export terminé'))
  } catch (error) {
    console.error('Export failed:', error)
    toast.error(t('users.toast.exportError', "Erreur lors de l'export"))
  }
}
</script>
