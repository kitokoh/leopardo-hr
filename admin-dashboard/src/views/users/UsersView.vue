<template>
  <div class="space-y-8 animate-fade-in">
    <!-- Premium Header -->
    <div class="card bg-brand-900 overflow-hidden relative border-0">
      <div class="absolute inset-0 brand-gradient opacity-90"></div>
      <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>

      <div class="relative px-8 py-8 md:flex md:items-center md:justify-between">
        <div class="flex-1 min-w-0">
          <h1 class="text-3xl font-extrabold text-white tracking-tight">Gestion des Utilisateurs</h1>
          <div class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-2">
            <div class="flex items-center text-brand-100 font-medium">
              <UsersIcon class="flex-shrink-0 mr-1.5 h-5 w-5 opacity-70" />
              <span>{{ stats.totalUsers }} Comptes au total</span>
            </div>
            <div class="flex items-center text-emerald-300 font-bold">
              <div class="h-2 w-2 rounded-full bg-emerald-400 mr-2 animate-pulse"></div>
              <span>{{ stats.activeUsers }} Actifs en ce moment</span>
            </div>
            <div class="flex items-center text-brand-100 font-medium">
              <PlusCircleIcon class="flex-shrink-0 mr-1.5 h-5 w-5 opacity-70" />
              <span>{{ stats.newToday }} Nouveaux aujourd'hui</span>
            </div>
          </div>
        </div>

        <div class="mt-6 md:mt-0 flex flex-wrap gap-3">
          <button
            @click="exportUsers"
            class="inline-flex items-center px-4 py-2.5 rounded-xl bg-white/10 backdrop-blur-md border border-white/20 text-sm font-bold text-white hover:bg-white/20 transition-all"
          >
            <DocumentArrowDownIcon class="h-4 w-4 mr-2" />
            Exporter CSV
          </button>
          <button
            @click="showCreateModal = true"
            class="inline-flex items-center px-5 py-2.5 rounded-xl bg-white text-sm font-extrabold text-brand-700 shadow-xl shadow-brand-950/20 hover:scale-105 active:scale-95 transition-all"
          >
            <UserPlusIcon class="h-4 w-4 mr-2" />
            Nouvel Utilisateur
          </button>
        </div>
      </div>
    </div>

    <!-- Smart Controls -->
    <div class="card p-4 sm:p-6 bg-white/80 backdrop-blur-sm sticky top-[4.5rem] z-30 shadow-lg">
      <div class="flex flex-col lg:flex-row gap-6">
        <!-- Search bar with focus effect -->
        <div class="flex-1">
          <label class="block text-[10px] font-bold text-zinc-400 uppercase tracking-widest mb-2 px-1">Recherche intelligente</label>
          <div class="relative group">
            <MagnifyingGlassIcon class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-zinc-400 group-focus-within:text-brand-500 transition-colors" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Nom, email, identifiant ou entreprise..."
              class="block w-full pl-11 pr-4 py-3 rounded-2xl border-zinc-200 bg-zinc-50/50 text-sm font-medium focus:bg-white focus:ring-4 focus:ring-brand-500/10 focus:border-brand-500 transition-all"
            />
          </div>
        </div>

        <!-- Inline filters -->
        <div class="flex flex-wrap items-end gap-4 lg:w-auto">
          <div class="w-full sm:w-40">
            <label class="block text-[10px] font-bold text-zinc-400 uppercase tracking-widest mb-2 px-1">Statut</label>
            <select
              v-model="filters.status"
              class="block w-full px-4 py-3 rounded-2xl border-zinc-200 bg-zinc-50/50 text-sm font-bold text-zinc-700 focus:bg-white focus:ring-4 focus:ring-brand-500/10 focus:border-brand-500 transition-all"
            >
              <option value="">Tous</option>
              <option value="active">Actif</option>
              <option value="inactive">Inactif</option>
              <option value="suspended">Suspendu</option>
              <option value="pending">En attente</option>
            </select>
          </div>

          <div class="w-full sm:w-40">
            <label class="block text-[10px] font-bold text-zinc-400 uppercase tracking-widest mb-2 px-1">Rôle</label>
            <select
              v-model="filters.role"
              class="block w-full px-4 py-3 rounded-2xl border-zinc-200 bg-zinc-50/50 text-sm font-bold text-zinc-700 focus:bg-white focus:ring-4 focus:ring-brand-500/10 focus:border-brand-500 transition-all"
            >
              <option value="">Tous les rôles</option>
              <option value="admin">Administrateur</option>
              <option value="manager">Manager</option>
              <option value="employee">Employé</option>
              <option value="hr">RH</option>
            </select>
          </div>

          <button
            @click="showAdvancedFilters = !showAdvancedFilters"
            :class="[
              'px-4 py-3 rounded-2xl border text-sm font-bold transition-all flex items-center gap-2',
              showAdvancedFilters ? 'bg-brand-50 border-brand-200 text-brand-700' : 'bg-zinc-50 border-zinc-200 text-zinc-500 hover:bg-zinc-100'
            ]"
          >
            <AdjustmentsHorizontalIcon class="h-5 w-5" />
            Filtres
          </button>
        </div>
      </div>

      <!-- Advanced Filters Expansion -->
      <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-4"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-4"
      >
        <div v-if="showAdvancedFilters" class="mt-6 pt-6 border-t border-zinc-100">
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div>
              <label class="block text-xs font-bold text-zinc-500 mb-2">Entreprise</label>
              <select v-model="filters.company" class="form-input rounded-2xl">
                <option value="">Toutes</option>
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-zinc-500 mb-2">Période d'inscription</label>
              <select v-model="filters.registrationDate" class="form-input rounded-2xl">
                <option value="">Indifférent</option>
                <option value="today">Aujourd'hui</option>
                <option value="week">7 derniers jours</option>
                <option value="month">30 derniers jours</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-zinc-500 mb-2">Dernière activité</label>
              <select v-model="filters.lastLogin" class="form-input rounded-2xl">
                <option value="">Toutes</option>
                <option value="today">Moins de 24h</option>
                <option value="week">Moins de 7 jours</option>
                <option value="never">Jamais connecté</option>
              </select>
            </div>
            <div class="flex items-end">
              <button @click="resetFilters" class="text-xs font-bold text-brand-600 hover:text-brand-700 pb-3">Réinitialiser tous les filtres</button>
            </div>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Bulk Actions Sticky Panel -->
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="translate-y-full opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-full opacity-0"
    >
      <div v-if="selectedUsers.length > 0" class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50 w-full max-w-2xl px-4">
        <div class="brand-gradient rounded-2xl px-6 py-4 shadow-2xl shadow-brand-500/40 flex items-center justify-between text-white border border-white/20 backdrop-blur-lg">
          <div class="flex items-center gap-4">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center font-extrabold">
              {{ selectedUsers.length }}
            </div>
            <span class="text-sm font-bold tracking-tight">Utilisateurs sélectionnés</span>
          </div>
          <div class="flex items-center gap-2">
            <button @click="bulkAction('activate')" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-bold transition-all border border-white/10">Activer</button>
            <button @click="bulkAction('suspend')" class="px-3 py-1.5 rounded-lg bg-rose-500 hover:bg-rose-600 text-xs font-bold transition-all border border-white/10 shadow-lg shadow-rose-900/20">Suspendre</button>
            <button @click="clearSelection" class="ml-2 text-white/60 hover:text-white transition-colors">
              <XMarkIcon class="h-6 w-6" />
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Table Section -->
    <div class="card overflow-hidden">
      <div class="p-6 bg-zinc-50/50 border-b border-zinc-100 flex items-center justify-between">
        <h2 class="text-lg font-bold text-zinc-900">Résultats détaillés</h2>
        <div class="flex items-center gap-2">
           <button @click="handleSelectAll(!isAllSelected)" class="text-xs font-bold text-zinc-500 hover:text-zinc-900 transition-colors">
             {{ isAllSelected ? 'Tout désélectionner' : 'Tout sélectionner' }}
           </button>
        </div>
      </div>

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

      <div class="px-6 py-5 bg-zinc-50/50 border-t border-zinc-100 flex flex-col sm:flex-row items-center justify-between gap-4">
        <p class="text-xs font-bold text-zinc-400 uppercase tracking-widest">
          Affichage {{ paginatedUsers.length }} sur {{ filteredUsers.length }}
        </p>
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
    <CreateUserModal v-if="showCreateModal" @close="showCreateModal = false" @created="handleUserCreated" />
    <EditUserModal v-if="showEditModal" :user="selectedUser" @close="showEditModal = false" @updated="handleUserUpdated" />
    <UserDetailModal v-if="showDetailModal" :user="selectedUser" @close="showDetailModal = false" @edit="editUser" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import {
  UserPlusIcon,
  DocumentArrowDownIcon,
  MagnifyingGlassIcon,
  AdjustmentsHorizontalIcon,
  XMarkIcon,
  UsersIcon,
  PlusCircleIcon
} from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'

// Components
import UserTable from '@/components/users/UserTable.vue'
import Pagination from '@/components/common/Pagination.vue'
import CreateUserModal from '@/components/users/CreateUserModal.vue'
import EditUserModal from '@/components/users/EditUserModal.vue'
import UserDetailModal from '@/components/users/UserDetailModal.vue'

const toast = useToast()

// State
const searchQuery = ref('')
const selectedUsers = ref([])
const selectedUser = ref(null)
const currentPage = ref(1)
const perPage = ref(25)
const isLoading = ref(false)
const showAdvancedFilters = ref(false)
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showDetailModal = ref(false)

const filters = reactive({
  status: '',
  role: '',
  company: '',
  registrationDate: '',
  lastLogin: '',
  segment: ''
})

const users = ref([])
const companies = ref([])
const stats = reactive({
  totalUsers: 0,
  activeUsers: 0,
  newToday: 0
})

const isAllSelected = computed(() =>
  paginatedUsers.value.length > 0 &&
  paginatedUsers.value.every(u => selectedUsers.value.includes(u.id))
)

onMounted(async () => {
  await loadUsers()
  await loadCompanies()
  updateStats()
})

const filteredUsers = computed(() => {
  let filtered = users.value
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    filtered = filtered.filter(u => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q) || u.company?.name.toLowerCase().includes(q))
  }
  if (filters.status) filtered = filtered.filter(u => u.status === filters.status)
  if (filters.role) filtered = filtered.filter(u => u.role === filters.role)
  if (filters.company) filtered = filtered.filter(u => u.company?.id === filters.company)
  return filtered
})

const totalPages = computed(() => Math.ceil(filteredUsers.value.length / perPage.value))
const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredUsers.value.slice(start, start + perPage.value)
})

watch([searchQuery, filters], () => { currentPage.value = 1 }, { deep: true })

async function loadUsers() {
  isLoading.value = true
  await new Promise(r => setTimeout(r, 800))
  users.value = generateMockUsers(150)
  isLoading.value = false
}

async function loadCompanies() {
  companies.value = [
    { id: 1, name: 'Acme Corp' },
    { id: 2, name: 'TechStart Inc' },
    { id: 3, name: 'Global Solutions' }
  ]
}

function generateMockUsers(count) {
  const mock = []
  const ss = ['active', 'inactive', 'suspended', 'pending']
  const rr = ['admin', 'manager', 'employee', 'hr']
  for (let i = 1; i <= count; i++) {
    mock.push({
      id: i,
      name: `User ${i}`,
      email: `user${i}@leopardo.io`,
      status: ss[Math.floor(Math.random() * ss.length)],
      role: rr[Math.floor(Math.random() * rr.length)],
      company: companies.value[Math.floor(Math.random() * companies.value.length)],
      createdAt: new Date(),
      lastLoginAt: new Date(),
      avatar: `https://api.dicebear.com/7.x/avataaars/svg?seed=user${i}`
    })
  }
  return mock
}

function updateStats() {
  stats.totalUsers = users.value.length
  stats.activeUsers = users.value.filter(u => u.status === 'active').length
  stats.newToday = 12
}

function handleUserSelect(id, selected) {
  if (selected) selectedUsers.value.push(id)
  else selectedUsers.value = selectedUsers.value.filter(x => x !== id)
}

function handleSelectAll(selected) {
  if (selected) selectedUsers.value = paginatedUsers.value.map(u => u.id)
  else selectedUsers.value = []
}

function clearSelection() { selectedUsers.value = [] }

function resetFilters() {
  Object.assign(filters, { status: '', role: '', company: '', registrationDate: '', lastLogin: '', segment: '' })
  searchQuery.value = ''
}

async function bulkAction(action) {
  toast.success(`Action ${action} appliquée sur ${selectedUsers.value.length} utilisateurs`)
  clearSelection()
}

function viewUser(u) { selectedUser.value = u; showDetailModal.value = true }
function editUser(u) { selectedUser.value = u; showEditModal.value = true }
function deleteUser() { if(confirm('Confirmer la suppression?')) toast.success('Supprimé') }
function impersonateUser(u) { toast.info(`Impersonification de ${u.name}`) }
function handleUserCreated() { showCreateModal.value = false; loadUsers() }
function handleUserUpdated() { showEditModal.value = false; loadUsers() }
async function exportUsers() { toast.info('Génération de l\'export...') }
</script>
