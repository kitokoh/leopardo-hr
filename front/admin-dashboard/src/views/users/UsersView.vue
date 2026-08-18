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
      @impersonate="openImpersonate"
    />

    <!-- Impersonation modal (PA2-ADM-006, issue #2518) -->
    <div v-if="showImpersonateModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4">
      <div class="w-full max-w-md rounded-2xl glass-card p-6">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white">
          {{ t('users.impersonation.title', 'Impersonner un employé') }}
        </h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">
          {{ t('users.impersonation.subtitle', 'Ouvrir une session au nom de :name').replace(':name', impersonation?.name || '') }}
        </p>

        <div v-if="!impersonationResult" class="mt-4 space-y-3">
          <div v-if="impersonation?.company" class="rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800">
            <p class="font-semibold text-gray-800 dark:text-slate-200">{{ impersonation.company.name || '—' }}</p>
            <p class="text-xs text-gray-500 dark:text-slate-400">
              {{ t('users.impersonation.employee', 'Employé #:id').replace(':id', impersonation.company.employee_id) }}
            </p>
          </div>
          <textarea
            v-model="impersonateReason"
            rows="3"
            class="w-full rounded-xl border border-slate-300 p-3 text-sm dark:border-slate-700 dark:bg-slate-800"
            :placeholder="t('users.impersonation.reason', 'Motif (obligatoire, 5 caractères minimum)')"
          ></textarea>
          <p v-if="impersonateError" class="text-sm font-medium text-red-600 dark:text-red-400">{{ impersonateError }}</p>
          <div class="flex justify-end gap-2">
            <button class="btn-secondary" @click="closeImpersonate">{{ t('users.impersonation.cancel', 'Annuler') }}</button>
            <button class="btn-primary" :disabled="impersonateBusy" @click="submitImpersonation">
              {{ impersonateBusy ? '…' : t('users.impersonation.start', 'Créer la session') }}
            </button>
          </div>
        </div>

        <div v-else class="mt-4 space-y-3">
          <div class="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/30">
            <p class="text-sm font-bold text-emerald-800 dark:text-emerald-300">
              {{ t('users.impersonation.tokenTitle', 'Jeton d’impersonation (usage unique)') }}
            </p>
            <code class="mt-2 block break-all rounded-lg glass-bg p-2 text-xs">{{ impersonationResult.token }}</code>
            <p class="mt-2 text-xs text-emerald-700 dark:text-emerald-400">
              {{ t('users.impersonation.expires', 'Expire le :date').replace(':date', new Date(impersonationResult.expires_at).toLocaleString(toIntlLocale(localeStore.current))) }}
            </p>
          </div>
          <div class="flex justify-end gap-2">
            <button class="btn-secondary" @click="copyImpersonationToken">{{ t('users.impersonation.copy', 'Copier le jeton') }}</button>
            <button class="btn-primary" @click="closeImpersonate">{{ t('users.impersonation.done', 'Terminé') }}</button>
          </div>
        </div>
      </div>
    </div>
  <ConfirmDialog
    :open="deleteOpen"
    :title="t('users.confirm.title', 'Supprimer cet utilisateur ?')"
    :message="deleteTarget ? t('users.confirm.delete', 'Êtes-vous sûr de vouloir supprimer :name ?').replace(':name', deleteTarget.name) : ''"
    confirm-label="Supprimer"
    @confirm="confirmDeleteUser"
    @cancel="deleteOpen = false"
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
import api from '@/services/api'
import { translate, toIntlLocale } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

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

// Pagination (server-side)
const paginatedUsers = computed(() => users.value)
// #2481 : sélection « tout » basée sur la liste chargée (la recherche est
// déjà server-side via /platform/users?search=…).
const filteredUsers = computed(() => users.value)

// #2481 : stats du résumé (totalItems alimenté par la réponse API).
// #2481 : export groupé = export client CSV de la page courante (aucun
// endpoint d'export groupé côté API).
function exportSelectedUsers() {
  // #3865 : l'export groupé respecte la sélection (avant : exportait toute
  // la page courante, sélection ignorée).
  const selected = users.value.filter(u => selectedUsers.value.includes(u.id))
  exportUsers(selected)
}
const usersSummary = computed(() => {
  const total = users.value.length
  const active = users.value.filter((u) => u.status === 'active' || u.status === 'activated').length
  const newToday = users.value.filter((u) => {
    if (!u.created_at) return false
    const d = new Date(u.created_at)
    const now = new Date()
    return d.toDateString() === now.toDateString()
  }).length
  return t('users.page.summary', '')
    .replace(':count', String(total))
    .replace(':active', String(active))
    .replace(':newToday', String(newToday))
})

onMounted(async () => {
  await loadUsers()
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
    // QA #2238 : données réelles via l'API /platform/users (issue #2229).
    // Le contrat API expose active/deactivated/suspended ; l'UI admin utilise
    // active/inactive/suspended/pending → mapping bidirectionnel ci-dessous.
    const params = {}
    if (searchQuery.value) params.search = searchQuery.value
    if (filters.status) {
      // Issue #2699 — 'pending' n'existe pas côté API : l'option a été retirée.
      params.status = { inactive: 'deactivated' }[filters.status] || filters.status
    }
    // Issue #2698 — pagination server-side réelle (page/per_page transmis).
    params.page = currentPage.value
    params.per_page = perPage.value

    const res = await api.get('/platform/users', { params })
    const items = res.data?.data ?? res.data ?? []
    users.value = items.map(user => ({
      id: user.id,
      name: user.name,
      email: user.email,
      status: user.status === 'deactivated' ? 'inactive' : user.status,
      // Issue #2701 — le payload /platform/users n'expose ni rôle ni société :
      // plus de valeur codée en dur, colonnes honnêtement vides.
      role: null,
      segment: null,
      company: user.company ?? null,
      createdAt: user.created_at ? new Date(user.created_at) : null,
      lastLoginAt: user.last_login_at ? new Date(user.last_login_at) : null,
      avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=random`
    }))
    // Issue #2698 — métadonnées de pagination renvoyées par l'API.
    const meta = res.data?.meta
    totalItems.value = meta?.total ?? users.value.length
    totalPages.value = meta?.last_page ?? Math.max(1, Math.ceil(users.value.length / perPage.value))
  } catch (error) {
    console.error('Failed to load users:', error)
    toast.error(t('users.toast.loadError', 'Erreur lors du chargement des utilisateurs'))
  } finally {
    isLoading.value = false
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
  try {
    // QA #2238 : actions réelles via l'API /platform/users/{id}/{action}.
    if (action !== 'export') {
      await Promise.all(selectedUsers.value.map(id =>
        api.post(`/platform/users/${id}/${action}`)
      ))
    } else {
      exportSelectedUsers()
      return
    }

    toast.success(t('users.toast.bulkDone', ':count utilisateur(s) mis à jour').replace(':count', String(selectedUsers.value.length)))
    clearSelection()
    await loadUsers()
  } catch (error) {
    console.error('Bulk action failed:', error)
    toast.error(t('users.toast.bulkError', "Erreur lors de l'action groupée"))
  }
}

async function viewUser(user) {
  // #3268 : le détail reste sur le contrat /platform/users/{id}, qui porte
  // désormais la liaison employé sans confondre les IDs super_admins et users.
  selectedUser.value = user
  showDetailModal.value = true
  try {
    const res = await api.get(`/platform/users/${user.id}`)
    const detail = res.data?.data ?? null
    if (detail) {
      selectedUser.value = {
        ...user,
        ...detail,
        company: detail.company ?? user.company ?? null,
      }
    }
  } catch (error) {
    console.warn('Failed to load impersonation link for user', user.id, error)
  }
}

// #2518 — Impersonation PA2-ADM-006 : POST /admin/impersonations avec motif.
const impersonation = ref(null)
const showImpersonateModal = ref(false)
const impersonateReason = ref('')
const impersonateBusy = ref(false)
const impersonateError = ref('')
const impersonationResult = ref(null)

function openImpersonate(user) {
  impersonation.value = user
  impersonateReason.value = ''
  impersonateError.value = ''
  impersonationResult.value = null
  showImpersonateModal.value = true
}

async function submitImpersonation() {
  const user = impersonation.value
  const company = user?.company
  if (!company?.id || !company?.employee_id) {
    impersonateError.value = t('users.impersonation.noLink', 'Aucun employé lié à ce compte — impersonation impossible.')
    return
  }
  if (impersonateReason.value.trim().length < 5) {
    impersonateError.value = t('users.impersonation.reasonMin', 'Motif obligatoire (5 caractères minimum).')
    return
  }
  impersonateBusy.value = true
  impersonateError.value = ''
  try {
    const res = await api.post('/platform/impersonations', {
      company_id: company.id,
      employee_id: company.employee_id,
      reason: impersonateReason.value.trim(),
    })
    impersonationResult.value = res.data
    toast.success(t('users.impersonation.created', 'Session d’impersonation créée'))
  } catch (error) {
    impersonateError.value = error.response?.data?.message
      || t('users.impersonation.error', "Erreur lors de la création de la session d'impersonation")
  } finally {
    impersonateBusy.value = false
  }
}

function copyImpersonationToken() {
  if (!impersonationResult.value?.token) return
  navigator.clipboard?.writeText(impersonationResult.value.token)
    .then(() => toast.success(t('users.impersonation.copied', 'Jeton copié')))
    // #4181 : ne JAMAIS exposer le jeton dans un toast (capture d'écran /
    // support) — message générique + renvoi vers le champ visible du modal.
    .catch(() => toast.error(
      t('users.impersonation.copyFailed', "Impossible de copier le jeton automatiquement. Copiez-le manuellement depuis le champ ci-dessus.")
    ))
}

function closeImpersonate() {
  showImpersonateModal.value = false
  impersonation.value = null
  impersonationResult.value = null
}

const deleteTarget = ref(null)
const deleteOpen = ref(false)

async function deleteUser(user) {
  deleteTarget.value = user
  deleteOpen.value = true
}

async function confirmDeleteUser() {
  const user = deleteTarget.value
  if (!user) return
  deleteOpen.value = false
  try {
    // Issue #2714 — désactivation RÉELLE via l'endpoint dédié (jamais de
    // suppression physique : DELETE /platform/users/{id} détruit le compte).
    await api.post(`/platform/users/${user.id}/deactivate`)
    toast.success(t('users.toast.deleted', 'Utilisateur désactivé'))
    await loadUsers()
  } catch (error) {
    console.error('Delete failed:', error)
    toast.error(t('users.toast.deleteError', 'Erreur lors de la suppression'))
  }
}

// Génère un mot de passe temporaire sûr (16 caractères) pour la création
// d'un utilisateur plateforme (exigence API : ≥ 12 caractères).
async function exportUsers(rows = null) {
  try {
    // Export honnête de la page courante (pas de mock) — CSV côté client.
    // Issue #2700 — exporte les champs MAPÉS (createdAt/lastLoginAt, sinon les
    // colonnes dates étaient toujours vides) + échappement anti-injection de
    // formule (cellules commençant par = + - @).
    // #3865 : `rows` optionnel — l'export groupé passe la sélection courante.
    const exportRows = rows !== null ? rows : users.value
    const escapeCell = (value) => {
      const str = value === null || value === undefined ? '' : String(value)
      if (/^[=+\-@]/.test(str)) return `'${str}`
      return `"${str.replace(/"/g, '""')}"`
    }
    const formatDate = (d) => (d instanceof Date && !Number.isNaN(d.getTime()))
      ? d.toLocaleDateString(toIntlLocale(localeStore.current))
      : ''
    // #4716 : en-têtes CSV localisés (clés users.csv.*).
    const tCsv = (key, fallback) => translate(localeStore.current, key, fallback)
    const csvContent = "data:text/csv;charset=utf-8," +
      [tCsv('users.csv.name', 'Nom'), tCsv('users.csv.email', 'Email'), tCsv('users.csv.status', 'Statut'), tCsv('users.csv.company', 'Entreprise'), tCsv('users.csv.signup', 'Inscription'), tCsv('users.csv.lastLogin', 'Dernière connexion')].join(',') + '\n' +
      exportRows.map(user =>
        [user.name, user.email, user.status, user.company?.name || '', formatDate(user.createdAt), formatDate(user.lastLoginAt)]
          .map(escapeCell).join(',')
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


