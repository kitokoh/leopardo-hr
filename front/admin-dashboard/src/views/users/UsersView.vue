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

          <!-- Issue #2269 : création d'utilisateur hors contrat backend v1 —
               retirée de l'UI (aucun bouton mort) -->

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
            @click="bulkAction('activate')"
            class="text-[10px] font-black uppercase tracking-widest text-emerald-600 hover:text-emerald-500 transition-colors"
          >
            {{ $t('users.bulkPanel.activate', 'Activer') }}
          </button>
          <button
            @click="bulkAction('deactivate')"
            class="text-[10px] font-black uppercase tracking-widest text-amber-600 hover:text-amber-500 transition-colors"
          >
            {{ $t('users.bulkPanel.deactivate', 'Désactiver') }}
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
        @delete="deleteUser"
        @impersonate="impersonateUser"
      />

      <!-- Pagination -->
      <div class="px-6 py-5 border-t border-slate-200/50 dark:border-slate-800/50">
        <Pagination
          :current-page="currentPage"
          :total-pages="totalPages"
          :total-items="totalItems"
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
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import {
  DocumentArrowDownIcon,
  CheckCircleIcon,
  MagnifyingGlassIcon,
  InformationCircleIcon
} from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import api from '@/services/api'

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
const totalPages = ref(1)
const totalItems = ref(0)
const isLoading = ref(false)
const showBulkActions = ref(false)
const showDetailModal = ref(false)

// Filters — seuls ceux supportés par le backend /admin/users (issue #2269)
const filters = reactive({
  status: ''
})

// Issue #2269 : plus de mocks — données réelles depuis GET /admin/users.
const users = ref([])
const companies = ref([])

// Pagination (server-side)
const paginatedUsers = computed(() => users.value)
const usersSummary = computed(() => {
  return t('users.page.summary', ':count utilisateur(s) plateforme')
    .replace(':count', String(totalItems.value))
})

onMounted(async () => {
  await Promise.all([loadUsers(), loadCompanies()])
})

// La recherche recharge la page 1 (debounce 300ms)
let searchTimer = null
watch(searchQuery, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    currentPage.value = 1
    loadUsers()
  }, 300)
})

watch(() => filters.status, () => {
  currentPage.value = 1
  loadUsers()
})

watch([currentPage, perPage], () => {
  loadUsers()
})

// Methods — donnees reelles (vague QA 2026-08-14, issue #2184)
async function loadUsers() {
  isLoading.value = true
  try {
    const params = new URLSearchParams({
      page: String(currentPage.value),
      per_page: String(perPage.value)
    })
    if (searchQuery.value.trim()) params.set('search', searchQuery.value.trim())
    if (filters.status) params.set('status', filters.status)

    const response = await api.get(`/admin/users?${params.toString()}`)
    const payload = response.data || {}
    users.value = (payload.data || []).map((user) => ({
      ...user,
      avatar: null,
      // UserTable affiche un statut par libellé connu
      status: user.is_active ? 'active' : 'inactive',
      role: user.roles?.[0]?.role || null,
      segment: null,
      lastLoginAt: user.last_login_at,
      createdAt: user.created_at
    }))
    totalItems.value = payload.meta?.total ?? users.value.length
    totalPages.value = payload.meta?.last_page ?? 1
    clearSelection()
  } catch (error) {
    console.error('Failed to load users:', error)
    toast.error(t('users.toast.loadError', 'Erreur lors du chargement des utilisateurs'))
  } finally {
    isLoading.value = false
  }
}

async function loadCompanies() {
  try {
    // Cockpit clients réel — alimente le filtre entreprise (si ré-ajouté).
    const response = await api.get('/platform/companies/health')
    const items = response.data?.data?.items || []
    companies.value = items.map((c) => ({ id: c.id, name: c.name }))
  } catch (error) {
    console.error('Failed to load companies:', error)
    companies.value = []
  }
}

function handleUserSelect(userId, checked) {
  if (checked) {
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
    return
  }

  const isActive = action === 'activate'
  let ok = 0

  try {
    for (const id of selectedUsers.value) {
      try {
        await api.patch(`/admin/users/${id}`, { is_active: isActive })
        ok++
      } catch (patchError) {
        console.error('Bulk action failed for user', id, patchError)
      }
    }

    if (ok === 0) {
      toast.error(t('users.toast.bulkError', "Erreur lors de l'action groupée"))
      return
    }

    toast.success(t('users.toast.bulkActivated', ':count utilisateur(s) mis à jour').replace(':count', String(ok)))
    clearSelection()
    await loadUsers()
  } catch (error) {
    console.error('Bulk action failed:', error)
    toast.error(t('users.toast.bulkError', "Erreur lors de l'action groupée"))
  }
}

function viewUser(user) {
  selectedUser.value = user
  showDetailModal.value = true
}

function deleteUser() {
  // Issue #2269 : pas d'endpoint de suppression utilisateur en v1 — l'action
  // est désactivée honnêtement (aucun bouton mort, aucune fausse promesse).
  toast.info(t('users.toast.deleteError', 'Suppression non disponible pour les utilisateurs plateforme en v1 — désactiver le compte à la place.'))
}

function impersonateUser() {
  // Pas de backend d'impersonation utilisateurs plateforme en v1 (issue #2269) :
  // l'action est retirée de l'UI (UserTable émet, la vue ignore) — aucun bouton mort.
  toast.info(t('users.toast.impersonating', 'Impersonation non disponible pour les utilisateurs plateforme en v1'))
}

async function exportUsers() {
  try {
    // Export honnête de la page courante (pas de mock) — CSV côté client.
    const csvContent = "data:text/csv;charset=utf-8," +
      "Nom,Email,Statut,Entreprise,Inscription,Dernière connexion\n" +
      users.value.map(user =>
        `${user.name},${user.email},${user.status},${user.company?.name || ''},${user.created_at || ''},${user.last_login_at || ''}`
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


