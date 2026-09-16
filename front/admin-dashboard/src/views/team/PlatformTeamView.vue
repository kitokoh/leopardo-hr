<template>
  <div class="space-y-6">
    <!-- En-tête -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="text-sm text-gray-500 dark:text-slate-400">{{ $t('team.platform.subtitle') }}</p>
        <p v-if="meta" class="mt-1 text-xs text-gray-400 dark:text-slate-500">
          {{ totalLabel }} · {{ activeSuperAdminsLabel }}
        </p>
      </div>
      <button
        type="button"
        data-testid="team-create-button"
        class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
        @click="openCreateModal"
      >
        <PlusIcon class="h-4 w-4" />
        {{ $t('team.platform.create') }}
      </button>
    </div>

    <!-- Liste des comptes internes -->
    <DataTable
      :columns="columns"
      :rows="members"
      :loading="loading"
      :error="loadError"
      :search-keys="['name', 'email', 'platform_role_label']"
      :search-placeholder="$t('team.platform.searchPlaceholder')"
      :empty-message="$t('team.platform.empty')"
      default-sort="name"
    >
      <template #cell-name="{ row }">
        <div class="flex items-center gap-2">
          <span class="font-medium text-slate-900 dark:text-white">{{ row.name }}</span>
          <span
            v-if="row.is_self"
            class="rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-brand-600 dark:bg-brand-900/30 dark:text-brand-300"
          >
            {{ $t('team.platform.you') }}
          </span>
        </div>
      </template>

      <template #cell-email="{ value }">
        <span class="text-xs text-gray-600 dark:text-slate-300">{{ value }}</span>
      </template>

      <template #cell-platform_role="{ row }">
        <div>
          <label class="sr-only" :for="`team-role-${row.id}`">{{ $t('team.platform.changeRole') }}</label>
          <select
            :id="`team-role-${row.id}`"
            :data-testid="`team-role-${row.id}`"
            :value="row.platform_role"
            :disabled="row.is_self || busyId === row.id"
            :title="row.is_self ? $t('team.platform.selfRoleLocked') : $t('team.platform.changeRole')"
            class="rounded-md border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
            @change="changeRole(row, $event.target.value)"
          >
            <option v-for="role in availableRoles" :key="role.value" :value="role.value">
              {{ role.label }}
            </option>
          </select>
          <p v-if="row.is_self" class="mt-1 text-[10px] text-gray-400 dark:text-slate-500">
            {{ $t('team.platform.selfRoleLocked') }}
          </p>
        </div>
      </template>

      <template #cell-status="{ value }">
        <span :class="statusClass(value)" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
          {{ statusLabel(value) }}
        </span>
      </template>

      <template #cell-last_login_at="{ value }">
        <span v-if="isValidDate(value)" class="text-xs text-gray-500 dark:text-slate-400">{{ formatDate(value) }}</span>
        <span v-else class="text-xs text-gray-400 dark:text-slate-500">{{ $t('team.platform.never') }}</span>
      </template>

      <template #row-actions="{ row }">
        <div class="flex justify-end gap-2">
          <RowActionButton
            v-if="row.status === 'active'"
            :icon="NoSymbolIcon"
            tone="danger"
            :disabled="row.is_self || busyId === row.id"
            :test-id="`team-deactivate-${row.id}`"
            :label="$t('team.platform.deactivate')"
            @click="askConfirmation('deactivate', row)"
          />
          <RowActionButton
            v-else
            :icon="CheckCircleIcon"
            tone="success"
            :disabled="busyId === row.id"
            :test-id="`team-activate-${row.id}`"
            :label="$t('team.platform.activate')"
            @click="askConfirmation('activate', row)"
          />
        </div>
      </template>
    </DataTable>

    <!-- Création d'un compte interne -->
    <div
      v-if="createOpen"
      data-testid="team-create-modal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-gray-600 bg-opacity-50 p-4"
      @click.self="closeCreateModal"
    >
      <div class="w-full max-w-lg glass-card p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ $t('team.platform.createTitle') }}
        </h3>
        <form class="mt-4 space-y-4" @submit.prevent="createMember">
          <div>
            <label for="team-name" class="block text-sm font-medium text-gray-700 dark:text-slate-300">
              {{ $t('team.platform.name') }}
            </label>
            <input
              id="team-name"
              v-model="form.name"
              type="text"
              required
              maxlength="100"
              class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            />
          </div>
          <div>
            <label for="team-email" class="block text-sm font-medium text-gray-700 dark:text-slate-300">
              {{ $t('team.platform.email') }}
            </label>
            <input
              id="team-email"
              v-model="form.email"
              type="email"
              required
              maxlength="150"
              class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            />
          </div>
          <div>
            <label for="team-password" class="block text-sm font-medium text-gray-700 dark:text-slate-300">
              {{ $t('team.platform.password') }}
            </label>
            <input
              id="team-password"
              v-model="form.password"
              type="password"
              required
              minlength="12"
              autocomplete="new-password"
              class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            />
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ $t('team.platform.passwordHint') }}</p>
          </div>
          <div>
            <label for="team-role" class="block text-sm font-medium text-gray-700 dark:text-slate-300">
              {{ $t('team.platform.role') }}
            </label>
            <select
              id="team-role"
              v-model="form.platform_role"
              required
              class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            >
              <option v-for="role in availableRoles" :key="role.value" :value="role.value">
                {{ role.label }}
              </option>
            </select>
          </div>

          <p
            v-if="createError"
            class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-300"
            role="alert"
          >
            {{ createError }}
          </p>

          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 dark:border-slate-700 dark:text-slate-300"
              @click="closeCreateModal"
            >
              {{ $t('team.platform.cancel') }}
            </button>
            <button
              type="submit"
              class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
              :disabled="saving"
            >
              {{ saving ? $t('team.platform.saving') : $t('team.platform.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- #3494 — confirmation in-app (jamais window.confirm) -->
    <div
      v-if="confirmState.open"
      data-testid="team-confirm-dialog"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4"
      @click.self="closeConfirmation"
    >
      <div class="w-full max-w-md rounded-2xl glass-card p-6">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white">
          {{ confirmState.action === 'deactivate' ? $t('team.platform.confirmDeactivateTitle') : $t('team.platform.confirmActivateTitle') }}
        </h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">
          {{ confirmState.action === 'deactivate' ? $t('team.platform.confirmDeactivateHint') : $t('team.platform.confirmActivateHint') }}
        </p>
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="closeConfirmation">{{ $t('team.platform.cancel') }}</button>
          <button
            type="button"
            data-testid="team-confirm-action"
            :class="confirmState.action === 'deactivate'
              ? 'rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50'
              : 'rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50'"
            :disabled="busyId === confirmState.member?.id"
            @click="confirmAction"
          >
            {{ confirmState.action === 'deactivate' ? $t('team.platform.deactivate') : $t('team.platform.activate') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { CheckCircleIcon, NoSymbolIcon, PlusIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import RowActionButton from '@/components/common/RowActionButton.vue'
import { useLocaleStore } from '@/stores/locale'
import { translate, toIntlLocale } from '@/i18n/index.js'

/**
 * #7557 — « Équipe plateforme » : le propriétaire du SaaS (rôle `super_admin`)
 * délègue une partie de l'administration à des comptes internes au lieu de
 * partager le compte omniscient.
 *
 * Contrat réel (#7553, `/api/v1` préfixé par le client) :
 *   GET    /platform/team                → { data:[member], meta:{total, active_super_admins, roles} }
 *   POST   /platform/team                → 201 { data:member }
 *   PATCH  /platform/team/{id}/role      → 200 { data:member } | 422 garde-fou
 *   POST   /platform/team/{id}/activate  → 200 { data:member }
 *   POST   /platform/team/{id}/deactivate→ 200 { data:member } | 422 garde-fou
 *
 * La garde `team.manage` n'est portée que par `super_admin` : l'écran n'est
 * donc visible que pour lui (cf. `src/navigation/navigation.js`), mais l'API
 * reste la source de vérité (403 PLATFORM_PERMISSION_REQUIRED sinon).
 */

const TEAM_ENDPOINT = '/platform/team'

/**
 * Rôles de repli si `meta.roles` n'est pas exposé par l'API.
 */
const PLATFORM_ROLE_VALUES = ['super_admin', 'admin', 'support', 'finance', 'ops', 'marketing']

/**
 * Libellés de rôle (catalogue `team.platform.roles.*`).
 */
const ROLE_LABEL_KEYS = {
  super_admin: 'team.platform.roles.super_admin',
  admin: 'team.platform.roles.admin',
  support: 'team.platform.roles.support',
  finance: 'team.platform.roles.finance',
  ops: 'team.platform.roles.ops',
  marketing: 'team.platform.roles.marketing',
}

/**
 * Libellés de statut (catalogue `team.platform.status*`).
 */
const STATUS_LABEL_KEYS = {
  active: 'team.platform.statusActive',
  deactivated: 'team.platform.statusDeactivated',
  suspended: 'team.platform.statusSuspended',
}

/**
 * Codes d'erreur de garde-fou → message localisé explicite (#7553).
 */
const GUARD_MESSAGE_KEYS = {
  CANNOT_CHANGE_OWN_PLATFORM_ROLE: 'team.platform.errorOwnRole',
  CANNOT_DISABLE_OWN_ACCOUNT: 'team.platform.errorOwnAccount',
  LAST_SUPER_ADMIN_REQUIRED: 'team.platform.errorLastSuperAdmin',
  PLATFORM_PERMISSION_REQUIRED: 'team.platform.errorPermissionRequired',
  PLATFORM_ACCOUNT_REQUIRED: 'team.platform.errorPlatformAccountRequired',
}

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const toast = useToast()

const members = ref([])
const meta = ref(null)
const loading = ref(false)
const saving = ref(false)
const loadError = ref('')
const busyId = ref(null)

const createOpen = ref(false)
const createError = ref('')
const form = ref({ name: '', email: '', password: '', platform_role: 'support' })

const confirmState = ref({ open: false, action: 'deactivate', member: null })

const totalLabel = computed(() =>
  t('team.platform.total', '{count} comptes').replace('{count}', String(meta.value?.total ?? members.value.length))
)

const activeSuperAdminsLabel = computed(() =>
  t('team.platform.activeSuperAdmins', '{count} super administrateur actif').replace(
    '{count}',
    String(meta.value?.active_super_admins ?? 0)
  )
)

const columns = computed(() => [
  { key: 'name', label: t('team.platform.name'), sortable: true },
  { key: 'email', label: t('team.platform.email'), sortable: true },
  { key: 'platform_role', label: t('team.platform.role') },
  { key: 'status', label: t('team.platform.status'), sortable: true },
  { key: 'last_login_at', label: t('team.platform.lastLogin'), sortable: true },
])

const availableRoles = computed(() => {
  const fromMeta = meta.value?.roles && typeof meta.value.roles === 'object' ? Object.keys(meta.value.roles) : []
  const values = fromMeta.length > 0 ? fromMeta : PLATFORM_ROLE_VALUES

  return values.map((value) => ({ value, label: t(ROLE_LABEL_KEYS[value] || value, value) }))
})

function statusLabel(status) {
  return t(STATUS_LABEL_KEYS[status] || 'team.platform.statusUnknown')
}

function statusClass(status) {
  if (status === 'active') {
    return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
  }
  if (status === 'suspended') {
    return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
  }

  return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
}

/**
 * `DataTable` remplace une valeur absente par un tiret : on valide donc
 * explicitement la date avant de la formater (un `new Date('-')` invalide
 * faisait échouer le rendu de toute la vue — `Intl.format` lève « Invalid
 * time value »).
 */
function isValidDate(value) {
  return typeof value === 'string' && !Number.isNaN(new Date(value).getTime())
}

function formatDate(value) {
  // #4517 : dates au format de la locale active (pas celle du navigateur).
  return new Intl.DateTimeFormat(toIntlLocale(localeStore.current), {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

/**
 * Message d'erreur le plus utile : le code de garde-fou (#7553) est prioritaire
 * — il est stable et traduit dans la locale de l'UI — puis le message serveur
 * (`localized_message`/`message`), puis le libellé générique de l'action.
 */
function errorMessage(error, fallbackKey) {
  const data = error?.response?.data
  const guardKey = data?.error ? GUARD_MESSAGE_KEYS[data.error] : null
  if (guardKey) {
    return t(guardKey)
  }

  return data?.localized_message || data?.message || t(fallbackKey)
}

async function fetchTeam() {
  loading.value = true
  loadError.value = ''
  try {
    const response = await api.get(TEAM_ENDPOINT)
    members.value = response.data?.data || []
    meta.value = response.data?.meta || null
  } catch (error) {
    loadError.value = errorMessage(error, 'team.platform.loadError')
    console.warn('Failed to load platform team', error)
    toast.error(loadError.value)
  } finally {
    loading.value = false
  }
}

async function changeRole(member, nextRole) {
  if (member.is_self) {
    // La garde est aussi désactivée côté UI (select disabled) : ceinture et
    // bretelles si un événement programmatique déclenche le changement.
    toast.error(t('team.platform.errorOwnRole'))
    return
  }
  if (nextRole === member.platform_role) return

  busyId.value = member.id
  try {
    const response = await api.patch(`${TEAM_ENDPOINT}/${member.id}/role`, { platform_role: nextRole })
    applyMember(response.data?.data)
    toast.success(t('team.platform.roleUpdated'))
  } catch (error) {
    // 422 CANNOT_CHANGE_OWN_PLATFORM_ROLE / LAST_SUPER_ADMIN_REQUIRED : la
    // garde-fou backend est affichée telle quelle (jamais avalée).
    console.warn('Failed to change platform role', error)
    toast.error(errorMessage(error, 'team.platform.roleUpdateError'))
    // Resynchronise l'état affiché (le select revient sur la valeur serveur).
    await fetchTeam()
  } finally {
    busyId.value = null
  }
}

async function createMember() {
  createError.value = ''

  if (form.value.password.length < 12) {
    createError.value = t('team.platform.passwordTooShort')
    return
  }

  saving.value = true
  try {
    await api.post(TEAM_ENDPOINT, { ...form.value })
    toast.success(t('team.platform.createSuccess'))
    closeCreateModal()
    await fetchTeam()
  } catch (error) {
    console.warn('Failed to create platform team member', error)
    createError.value = errorMessage(error, 'team.platform.createError')
    toast.error(createError.value)
  } finally {
    saving.value = false
  }
}

function openCreateModal() {
  form.value = { name: '', email: '', password: '', platform_role: 'support' }
  createError.value = ''
  createOpen.value = true
}

function closeCreateModal() {
  createOpen.value = false
  createError.value = ''
}

function askConfirmation(action, member) {
  confirmState.value = { open: true, action, member }
}

function closeConfirmation() {
  confirmState.value = { ...confirmState.value, open: false }
}

async function confirmAction() {
  const { action, member } = confirmState.value
  if (!member) return

  busyId.value = member.id
  try {
    const response = await api.post(`${TEAM_ENDPOINT}/${member.id}/${action}`)
    applyMember(response.data?.data)
    closeConfirmation()
    toast.success(action === 'activate' ? t('team.platform.activated') : t('team.platform.deactivated'))
  } catch (error) {
    console.warn('Failed to change platform account status', error)
    toast.error(errorMessage(error, 'team.platform.actionError'))
    closeConfirmation()
    await fetchTeam()
  } finally {
    busyId.value = null
  }
}

/**
 * Remplace la ligne concernée par la version renvoyée par l'API.
 */
function applyMember(updated) {
  if (!updated?.id) {
    fetchTeam()
    return
  }

  members.value = members.value.map((member) => (member.id === updated.id ? updated : member))
}

onMounted(fetchTeam)
</script>
