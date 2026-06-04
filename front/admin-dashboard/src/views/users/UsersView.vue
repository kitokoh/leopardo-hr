<template>
  <div class="space-y-8 animate-fade-in">
    <!-- Header with actions -->
    <div class="card p-8 relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-64 h-64 bg-brand-500/10 rounded-full blur-3xl"></div>

      <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
          <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white mb-2">Gestion des Utilisateurs</h1>
          <p class="text-slate-500 dark:text-slate-400 font-medium">
            {{ filteredUsers.length }} utilisateur(s) • <span class="text-emerald-600 dark:text-emerald-400">{{ stats.activeUsers }} actif(s)</span> • <span class="text-brand-600 dark:text-brand-400">{{ stats.newToday }} nouveau(x) aujourd'hui</span>
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button
            @click="showBulkActions = !showBulkActions"
            :class="[
              'inline-flex items-center px-3 py-2 border text-sm font-medium rounded-md',
              selectedUsers.length > 0
                ? 'border-indigo-300 text-indigo-700 bg-indigo-50 hover:bg-indigo-100'
                : 'border-gray-300 text-gray-700 bg-white hover:bg-gray-50'
            ]"
          >
            <CheckCircleIcon class="h-4 w-4 mr-2" />
            Actions groupées ({{ selectedUsers.length }})
          </button>

          <button
            @click="exportUsers"
            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
          >
            <DocumentArrowDownIcon class="h-4 w-4 mr-2" />
            Exporter
          </button>

          <button
            @click="showCreateModal = true"
            class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
          >
            <UserPlusIcon class="h-4 w-4 mr-2" />
            Nouvel utilisateur
          </button>
        </div>
      </div>
    </div>

    <!-- Filters and Search -->
    <div class="card p-8 animate-slide-up" style="animation-delay: 0.1s">
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Search -->
        <div class="lg:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Rechercher</label>
          <div class="relative">
            <MagnifyingGlassIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Nom, email, entreprise..."
              class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
            />
          </div>
        </div>

        <!-- Status Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
          <select
            v-model="filters.status"
            class="block w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
          >
            <option value="">Tous les statuts</option>
            <option value="active">Actif</option>
            <option value="inactive">Inactif</option>
            <option value="suspended">Suspendu</option>
            <option value="pending">En attente</option>
          </select>
        </div>

        <!-- Role Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Rôle</label>
          <select
            v-model="filters.role"
            class="block w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
          >
            <option value="">Tous les rôles</option>
            <option value="admin">Administrateur</option>
            <option value="manager">Manager</option>
            <option value="employee">Employé</option>
            <option value="hr">RH</option>
          </select>
        </div>
      </div>

      <!-- Advanced Filters Toggle -->
      <div class="mt-4">
        <button
          @click="showAdvancedFilters = !showAdvancedFilters"
          class="text-sm text-indigo-600 hover:text-indigo-500 font-medium"
        >
          {{ showAdvancedFilters ? 'Masquer' : 'Afficher' }} les filtres avancés
        </button>
      </div>

      <!-- Advanced Filters -->
      <div v-if="showAdvancedFilters" class="mt-4 pt-4 border-t border-gray-200">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Entreprise</label>
            <select
              v-model="filters.company"
              class="block w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
            >
              <option value="">Toutes les entreprises</option>
              <option v-for="company in companies" :key="company.id" :value="company.id">
                {{ company.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date d'inscription</label>
            <select
              v-model="filters.registrationDate"
              class="block w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
            >
              <option value="">Toutes les dates</option>
              <option value="today">Aujourd'hui</option>
              <option value="week">Cette semaine</option>
              <option value="month">Ce mois</option>
              <option value="quarter">Ce trimestre</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Dernière connexion</label>
            <select
              v-model="filters.lastLogin"
              class="block w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
            >
              <option value="">Toutes</option>
              <option value="today">Aujourd'hui</option>
              <option value="week">Cette semaine</option>
              <option value="month">Ce mois</option>
              <option value="never">Jamais connecté</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Segment</label>
            <select
              v-model="filters.segment"
              class="block w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
            >
              <option value="">Tous les segments</option>
              <option value="champions">Champions</option>
              <option value="loyal">Loyaux</option>
              <option value="potential">Potentiels</option>
              <option value="new">Nouveaux</option>
              <option value="at-risk">À risque</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Bulk Actions Panel -->
    <div v-if="showBulkActions && selectedUsers.length > 0" class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <InformationCircleIcon class="h-5 w-5 text-indigo-400 mr-2" />
          <span class="text-sm font-medium text-indigo-800">
            {{ selectedUsers.length }} utilisateur(s) sélectionné(s)
          </span>
        </div>

        <div class="flex items-center space-x-3">
          <button
            @click="bulkAction('activate')"
            class="text-sm font-medium text-green-700 hover:text-green-600"
          >
            Activer
          </button>
          <button
            @click="bulkAction('deactivate')"
            class="text-sm font-medium text-yellow-700 hover:text-yellow-600"
          >
            Désactiver
          </button>
          <button
            @click="bulkAction('suspend')"
            class="text-sm font-medium text-red-700 hover:text-red-600"
          >
            Suspendre
          </button>
          <button
            @click="bulkAction('export')"
            class="text-sm font-medium text-indigo-700 hover:text-indigo-600"
          >
            Exporter sélection
          </button>
          <button
            @click="clearSelection"
            class="text-sm font-medium text-gray-500 hover:text-gray-400"
          >
            Annuler
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
  InformationCircleIcon
} from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'

// Components
import UserTable from '@/components/users/UserTable.vue'
import Pagination from '@/components/common/Pagination.vue'
import CreateUserModal from '@/components/users/CreateUserModal.vue'
import EditUserModal from '@/components/users/EditUserModal.vue'
import UserDetailModal from '@/components/users/UserDetailModal.vue'

const toast = useToast()

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
    toast.error('Erreur lors du chargement des utilisateurs')
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
        toast.success(`${selectedUsers.value.length} utilisateur(s) activé(s)`)
        break
      case 'deactivate':
        toast.success(`${selectedUsers.value.length} utilisateur(s) désactivé(s)`)
        break
      case 'suspend':
        toast.success(`${selectedUsers.value.length} utilisateur(s) suspendu(s)`)
        break
      case 'export':
        exportSelectedUsers()
        return
    }

    clearSelection()
    await loadUsers()
  } catch (error) {
    console.error('Bulk action failed:', error)
    toast.error('Erreur lors de l\'action groupée')
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
  if (confirm(`Êtes-vous sûr de vouloir supprimer ${user.name} ?`)) {
    try {
      // Simulate API call
      await new Promise(resolve => setTimeout(resolve, 500))

      toast.success('Utilisateur supprimé')
      await loadUsers()
    } catch (error) {
      console.error('Delete failed:', error)
      toast.error('Erreur lors de la suppression')
    }
  }
}

function impersonateUser(user) {
  toast.info(`Connexion en tant que ${user.name}`)
  // Implement impersonation logic
}

function handleUserCreated(user) {
  toast.success('Utilisateur créé avec succès')
  showCreateModal.value = false
  loadUsers()
}

function handleUserUpdated(user) {
  toast.success('Utilisateur mis à jour')
  showEditModal.value = false
  loadUsers()
}

async function exportUsers() {
  try {
    toast.info('Export en cours...')

    // Simulate export
    await new Promise(resolve => setTimeout(resolve, 2000))

    const csvContent = "data:text/csv;charset=utf-8," +
      "Nom,Email,Statut,Rôle,Entreprise,Date d'inscription\n" +
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

    toast.success('Export terminé')
  } catch (error) {
    console.error('Export failed:', error)
    toast.error('Erreur lors de l\'export')
  }
}

function exportSelectedUsers() {
  const selectedUserData = users.value.filter(u => selectedUsers.value.includes(u.id))

  const csvContent = "data:text/csv;charset=utf-8," +
    "Nom,Email,Statut,Rôle,Entreprise,Date d'inscription\n" +
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

  toast.success('Export de la sélection terminé')
}
</script>