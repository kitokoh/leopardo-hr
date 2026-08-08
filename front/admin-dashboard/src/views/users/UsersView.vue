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
                : 'border-slate-200 text-slate-700 bg-white/50 dark:bg-slate-900/50 hover:glass-card dark:border-slate-800 dark:text-slate-300 dark:bg-slate-900/50'
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

          <button
            @click="showCreateModal = true"
            class="btn-primary py-2.5 text-xs font-black uppercase tracking-widest shadow-premium"
          >
            <UserPlusIcon class="h-4 w-4 mr-2" />
            {{ $t('users.actions.new', 'Nouveau') }}
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

        <!-- Role Filter -->
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2 ml-1">{{ $t('users.filters.role.label', 'RÃ´le') }}</label>
          <select
            v-model="filters.role"
            class="block w-full px-4 py-3 bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-sm font-bold focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all outline-none appearance-none"
          >
            <option value="">{{ $t('users.filters.role.all', 'Tous les rÃ´les') }}</option>
            <option value="admin">{{ $t('users.filters.role.admin', 'Administrateur') }}</option>
            <option value="manager">{{ $t('users.filters.role.manager', 'Manager') }}</option>
            <option value="employee">{{ $t('users.filters.role.employee', 'EmployÃ©') }}</option>
            <option value="hr">{{ $t('users.filters.role.hr', 'RH') }}</option>
          </select>
        </div>
      </div>

      <!-- Advanced Filters Toggle -->
      <div class="mt-6">
        <button
          @click="showAdvancedFilters = !showAdvancedFilters"
          class="text-xs font-black uppercase tracking-widest text-brand-600 hover:text-brand-700 dark:text-brand-400 transition-colors flex items-center"
        >
          <span>{{ showAdvancedFilters ? $t('users.filters.advanced.hide', 'Masquer les filtres avancÃ©s') : $t('users.filters.advanced.show', 'Afficher les filtres avancÃ©s') }}</span>
          <ChevronDownIcon :class="['ml-2 h-4 w-4 transition-transform duration-300', showAdvancedFilters ? 'rotate-180' : '']" />
        </button>
      </div>

      <!-- Advanced Filters -->
      <div v-if="showAdvancedFilters" class="mt-4 pt-4 border-t border-gray-200">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('users.filters.company.label', 'Entreprise') }}</label>
            <select
              v-model="filters.company"
              class="block w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
            >
              <option value="">{{ $t('users.filters.company.all', 'Toutes les entreprises') }}</option>
              <option v-for="company in companies" :key="company.id" :value="company.id">
                {{ company.name }}
              </option>
            </select>
          </div>

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

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('users.filters.lastLogin.label', 'DerniÃ¨re connexion') }}</label>
            <select
              v-model="filters.lastLogin"
              class="block w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
            >
              <option value="">{{ $t('users.filters.lastLogin.all', 'Toutes') }}</option>
              <option value="today">{{ $t('users.filters.lastLogin.today', "Aujourd'hui") }}</option>
              <option value="week">{{ $t('users.filters.lastLogin.week', 'Cette semaine') }}</option>
              <option value="month">{{ $t('users.filters.lastLogin.month', 'Ce mois') }}</option>
              <option value="never">{{ $t('users.filters.lastLogin.never', 'Jamais connectÃ©') }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('users.filters.segment.label', 'Segment') }}</label>
            <select
              v-model="filters.segment"
              class="block w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
            >
              <option value="">{{ $t('users.filters.segment.all', 'Tous les segments') }}</option>
              <option value="champions">{{ $t('users.filters.segment.champions', 'Champions') }}</option>
              <option value="loyal">{{ $t('users.filters.segment.loyal', 'Loyaux') }}</option>
              <option value="potential">{{ $t('users.filters.segment.potential', 'Potentiels') }}</option>
              <option value="new">{{ $t('users.filters.segment.new', 'Nouveaux') }}</option>
              <option value="at-risk">{{ $t('users.filters.segment.atRisk', 'Ã€ risque') }}</option>
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
            {{ $t('users.bulkPanel.selectedCount', ':count sÃ©lectionnÃ©(s)').replace(':count', String(selectedUsers.length)) }}
          </span>
        </div>

        <div class="flex items-center space-x-6">
          <button
            @click="bulkAction('activate')"
            class="text-[10px] font-black uppercase tracking-widest text-emerald-600 hover:text-emerald-500 transition-colors"
          >
            {{ $t('users.bulkPanel.activate', 'Activer') }}
          </button>
          <button
            @click="bulkAction('deactivate')"
            class="text-[10px] font-black uppercase tracking-widest text-amber-600 hover:text-amber-500 transition-colors"
          >
            {{ $t('users.bulkPanel.deactivate', 'DÃ©sactiver') }}
          </button>
          <button
            @click="bulkAction('suspend')"
            class="text-[10px] font-black uppercase tracking-widest text-red-600 hover:text-red-500 transition-colors"
          >
            {{ $t('users.bulkPanel.suspend', 'Suspendre') }}
          </button>
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
        @edit="editUser"
        @delete="deleteUser"
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
    <CreateUserModal
      v-if="showCreateModal"
      @close="showCreateModal = false"
      @created="handleUserCreated"
    />

    <EditUserModal
      v-if="showEditModal"
      :user="selectedUser"
      @close="showEditModal = false"
      @updated="handleUserUpdated"
    />

    <UserDetailModal
      v-if="showDetailModal"
      :user="selectedUser"
      @close="showDetailModal = false"
      @edit="editUser"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import {
  UserPlusIcon,
  DocumentArrowDownIcon,
  CheckCircleIcon,
  MagnifyingGlassIcon,
  InformationCircleIcon,
  ChevronDownIcon
} from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

// Components
import UserTable from '@/components/users/UserTable.vue'
import Pagination from '@/components/common/Pagination.vue'
import CreateUserModal from '@/components/users/CreateUserModal.vue'
import EditUserModal from '@/components/users/EditUserModal.vue'
import UserDetailModal from '@/components/users/UserDetailModal.vue'

const toast = useToast()
const localeStore = useLocaleStore()

// Local i18n helper for use inside <script setup> (mirrors the global $t exposed to templates)
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
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showDetailModal = ref(false)

// Filters
const filters = reactive({
  status: '',
  role: '',
  company: '',
  registrationDate: '',
  lastLogin: '',
  segment: ''
})

// Mock data
const users = ref([])
const companies = ref([])
const stats = reactive({
  totalUsers: 0,
  activeUsers: 0,
  newToday: 0
})

onMounted(async () => {
  await loadUsers()
  await loadCompanies()
  updateStats()
})

// Computed properties
const filteredUsers = computed(() => {
  let filtered = users.value

  // Search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(user =>
      user.name.toLowerCase().includes(query) ||
      user.email.toLowerCase().includes(query) ||
      user.company?.name.toLowerCase().includes(query)
    )
  }

  // Status filter
  if (filters.status) {
    filtered = filtered.filter(user => user.status === filters.status)
  }

  // Role filter
  if (filters.role) {
    filtered = filtered.filter(user => user.role === filters.role)
  }

  // Company filter
  if (filters.company) {
    filtered = filtered.filter(user => user.company?.id === filters.company)
  }

  // Registration date filter
  if (filters.registrationDate) {
    const now = new Date()
    filtered = filtered.filter(user => {
      const userDate = new Date(user.createdAt)
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
  return Math.ceil(filteredUsers.value.length / perPage.value)
})

const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  const end = start + perPage.value
  return filteredUsers.value.slice(start, end)
})

const usersSummary = computed(() => {
  return t('users.page.summary', ":count utilisateur(s) - :active actif(s) - :newToday nouveau(x) aujourd'hui")
    .replace(':count', String(filteredUsers.value.length))
    .replace(':active', String(stats.activeUsers))
    .replace(':newToday', String(stats.newToday))
})

// Watch for filter changes to reset pagination
watch([searchQuery, filters], () => {
  currentPage.value = 1
}, { deep: true })

// Methods
async function loadUsers() {
  isLoading.value = true

  try {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000))

    // Generate mock users
    users.value = generateMockUsers(150)
  } catch (error) {
    console.error('Failed to load users:', error)
    toast.error(t('users.toast.loadError', 'Erreur lors du chargement des utilisateurs'))
  } finally {
    isLoading.value = false
  }
}

async function loadCompanies() {
  // Generate mock companies
  companies.value = [
    { id: 1, name: 'Acme Corp' },
    { id: 2, name: 'TechStart Inc' },
    { id: 3, name: 'Global Solutions' },
    { id: 4, name: 'Innovation Labs' },
    { id: 5, name: 'Digital Dynamics' }
  ]
}

function generateMockUsers(count) {
  const mockUsers = []
  const statuses = ['active', 'inactive', 'suspended', 'pending']
  const roles = ['admin', 'manager', 'employee', 'hr']
  const segments = ['champions', 'loyal', 'potential', 'new', 'at-risk']

  for (let i = 1; i <= count; i++) {
    mockUsers.push({
      id: i,
      name: `Utilisateur ${i}`,
      email: `user${i}@example.com`,
      status: statuses[Math.floor(Math.random() * statuses.length)],
      role: roles[Math.floor(Math.random() * roles.length)],
      segment: segments[Math.floor(Math.random() * segments.length)],
      company: companies.value[Math.floor(Math.random() * companies.value.length)],
      createdAt: new Date(Date.now() - Math.random() * 365 * 24 * 60 * 60 * 1000),
      lastLoginAt: Math.random() > 0.2 ? new Date(Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000) : null,
      avatar: `https://ui-avatars.com/api/?name=Utilisateur+${i}&background=random`
    })
  }

  return mockUsers
}

function updateStats() {
  stats.totalUsers = users.value.length
  stats.activeUsers = users.value.filter(u => u.status === 'active').length

  const today = new Date().toDateString()
  stats.newToday = users.value.filter(u =>
    new Date(u.createdAt).toDateString() === today
  ).length
}

function handleUserSelect(userId, selected) {
  if (selected) {
    selectedUsers.value.push(userId)
  } else {
    selectedUsers.value = selectedUsers.value.filter(id => id !== userId)
  }
}

function handleSelectAll(selected) {
  if (selected) {
    selectedUsers.value = paginatedUsers.value.map(u => u.id)
  } else {
    selectedUsers.value = []
  }
}

function clearSelection() {
  selectedUsers.value = []
  showBulkActions.value = false
}

async function bulkAction(action) {
  try {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000))

    switch (action) {
      case 'activate':
        toast.success(t('users.toast.bulkActivated', ':count utilisateur(s) activÃ©(s)').replace(':count', String(selectedUsers.value.length)))
        break
      case 'deactivate':
        toast.success(t('users.toast.bulkDeactivated', ':count utilisateur(s) dÃ©sactivÃ©(s)').replace(':count', String(selectedUsers.value.length)))
        break
      case 'suspend':
        toast.success(t('users.toast.bulkSuspended', ':count utilisateur(s) suspendu(s)').replace(':count', String(selectedUsers.value.length)))
        break
      case 'export':
        exportSelectedUsers()
        return
    }

    clearSelection()
    await loadUsers()
  } catch (error) {
    console.error('Bulk action failed:', error)
    toast.error(t('users.toast.bulkError', "Erreur lors de l'action groupÃ©e"))
  }
}

function viewUser(user) {
  selectedUser.value = user
  showDetailModal.value = true
}

function editUser(user) {
  selectedUser.value = user
  showEditModal.value = true
}

async function deleteUser(user) {
  if (confirm(t('users.confirm.delete', 'ÃŠtes-vous sÃ»r de vouloir supprimer :name ?').replace(':name', user.name))) {
    try {
      // Simulate API call
      await new Promise(resolve => setTimeout(resolve, 500))

      toast.success(t('users.toast.deleted', 'Utilisateur supprimÃ©'))
      await loadUsers()
    } catch (error) {
      console.error('Delete failed:', error)
      toast.error(t('users.toast.deleteError', 'Erreur lors de la suppression'))
    }
  }
}

function impersonateUser(user) {
  toast.info(t('users.toast.impersonating', 'Connexion en tant que :name').replace(':name', user.name))
  // Implement impersonation logic
}

function handleUserCreated() {
  toast.success(t('users.toast.created', 'Utilisateur crÃ©Ã© avec succÃ¨s'))
  showCreateModal.value = false
  loadUsers()
}

function handleUserUpdated() {
  toast.success(t('users.toast.updated', 'Utilisateur mis Ã  jour'))
  showEditModal.value = false
  loadUsers()
}

async function exportUsers() {
  try {
    toast.info(t('users.toast.exportInProgress', 'Export en cours...'))

    // Simulate export
    await new Promise(resolve => setTimeout(resolve, 2000))

    const csvContent = "data:text/csv;charset=utf-8," +
      "Nom,Email,Statut,RÃ´le,Entreprise,Date d'inscription\n" +
      filteredUsers.value.map(user =>
        `${user.name},${user.email},${user.status},${user.role},${user.company?.name || ''},${user.createdAt.toLocaleDateString()}`
      ).join('\n')

    const encodedUri = encodeURI(csvContent)
    const link = document.createElement("a")
    link.setAttribute("href", encodedUri)
    link.setAttribute("download", "users-export.csv")
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)

    toast.success(t('users.toast.exportDone', 'Export terminÃ©'))
  } catch (error) {
    console.error('Export failed:', error)
    toast.error(t('users.toast.exportError', "Erreur lors de l'export"))
  }
}

function exportSelectedUsers() {
  const selectedUserData = users.value.filter(u => selectedUsers.value.includes(u.id))

  const csvContent = "data:text/csv;charset=utf-8," +
    "Nom,Email,Statut,RÃ´le,Entreprise,Date d'inscription\n" +
    selectedUserData.map(user =>
      `${user.name},${user.email},${user.status},${user.role},${user.company?.name || ''},${user.createdAt.toLocaleDateString()}`
    ).join('\n')

  const encodedUri = encodeURI(csvContent)
  const link = document.createElement("a")
  link.setAttribute("href", encodedUri)
  link.setAttribute("download", "selected-users-export.csv")
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)

  toast.success(t('users.toast.selectionExportDone', 'Export de la sÃ©lection terminÃ©'))
}
</script>

