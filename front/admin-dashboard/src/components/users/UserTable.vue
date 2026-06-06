<template>
  <div class="overflow-hidden">
    <table class="min-w-full divide-y divide-slate-200/50 dark:divide-slate-800/50">
      <thead class="bg-slate-50/50 dark:bg-slate-800/50">
        <tr>
          <th scope="col" class="relative w-12 px-6 sm:w-16 sm:px-8">
            <input
              type="checkbox"
              :checked="isAllSelected"
              :indeterminate="isIndeterminate"
              @change="$emit('select-all', $event.target.checked)"
              class="absolute left-4 top-1/2 -mt-2 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-600"
            />
          </th>
          <th
            v-for="column in columns"
            :key="column.key"
            scope="col"
            :class="[
              'px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider',
              column.sortable ? 'cursor-pointer hover:text-slate-700 dark:hover:text-slate-200 transition-colors' : ''
            ]"
            @click="column.sortable && handleSort(column.key)"
          >
            <div class="flex items-center space-x-1.5">
              <span>{{ column.label }}</span>
              <div v-if="column.sortable" class="flex flex-col">
                <ChevronUpIcon
                  :class="[
                    'h-3.5 w-3.5',
                    sortBy === column.key && sortOrder === 'asc' ? 'text-brand-500' : 'text-slate-300'
                  ]"
                />
                <ChevronDownIcon
                  :class="[
                    'h-3.5 w-3.5 -mt-1.5',
                    sortBy === column.key && sortOrder === 'desc' ? 'text-brand-500' : 'text-slate-300'
                  ]"
                />
              </div>
            </div>
          </th>
          <th scope="col" class="relative py-3 pl-3 pr-4 sm:pr-6">
            <span class="sr-only">Actions</span>
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200/50 dark:divide-slate-800/50 bg-white/40 dark:bg-slate-900/40 backdrop-blur-sm">
        <!-- Loading state -->
        <tr v-if="loading">
          <td colspan="8" class="px-6 py-16 text-center">
            <div class="flex flex-col items-center justify-center">
              <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-brand-600 mb-4"></div>
              <span class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Chargement des utilisateurs...</span>
            </div>
          </td>
        </tr>

        <!-- Empty state -->
        <tr v-else-if="users.length === 0">
          <td colspan="8" class="px-6 py-12 text-center">
            <UsersIcon class="mx-auto h-12 w-12 text-gray-400" />
            <h3 class="mt-2 text-sm font-medium text-gray-900">Aucun utilisateur</h3>
            <p class="mt-1 text-sm text-gray-500">
              Aucun utilisateur ne correspond aux critères de recherche.
            </p>
          </td>
        </tr>

        <!-- User rows -->
        <tr
          v-for="user in sortedUsers"
          :key="user.id"
          :class="[
            'hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors',
            selectedUsers.includes(user.id) ? 'bg-brand-50/50 dark:bg-brand-900/10' : ''
          ]"
        >
          <td class="relative w-12 px-6 sm:w-16 sm:px-8">
            <input
              type="checkbox"
              :checked="selectedUsers.includes(user.id)"
              @change="$emit('select', user.id, $event.target.checked)"
              class="absolute left-4 top-1/2 -mt-2 h-4 w-4 rounded-md border-slate-300 text-brand-600 focus:ring-brand-600"
            />
          </td>

          <!-- Avatar & Name -->
          <td class="whitespace-nowrap px-6 py-5">
            <div class="flex items-center">
              <div class="h-10 w-10 flex-shrink-0 relative">
                <img
                  :src="user.avatar"
                  :alt="user.name"
                  class="h-10 w-10 rounded-xl shadow-sm"
                />
                <div
                  v-if="user.status === 'active'"
                  class="absolute -bottom-1 -right-1 h-3.5 w-3.5 rounded-full border-2 border-white dark:border-slate-900 bg-emerald-500 shadow-sm"
                ></div>
              </div>
              <div class="ml-4">
                <div class="text-sm font-bold text-slate-900 dark:text-white">{{ user.name }}</div>
                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ user.email }}</div>
              </div>
            </div>
          </td>

          <!-- Status -->
          <td class="whitespace-nowrap px-6 py-5">
            <span :class="getStatusColor(user.status)">
              {{ getStatusLabel(user.status) }}
            </span>
          </td>

          <!-- Role -->
          <td class="whitespace-nowrap px-6 py-5 text-sm text-slate-900">
            <span :class="getRoleColor(user.role)">
              {{ getRoleLabel(user.role) }}
            </span>
          </td>

          <!-- Company -->
          <td class="whitespace-nowrap px-6 py-5 text-sm font-semibold text-slate-700 dark:text-slate-300">
            {{ user.company?.name || '-' }}
          </td>

          <!-- Segment -->
          <td class="whitespace-nowrap px-6 py-5">
            <span :class="getSegmentColor(user.segment)">
              {{ getSegmentLabel(user.segment) }}
            </span>
          </td>

          <!-- Last Login -->
          <td class="whitespace-nowrap px-6 py-5 text-sm font-medium text-slate-500 dark:text-slate-400">
            {{ formatLastLogin(user.lastLoginAt) }}
          </td>

          <!-- Created At -->
          <td class="whitespace-nowrap px-6 py-5 text-sm font-medium text-slate-500 dark:text-slate-400">
            {{ formatDate(user.createdAt) }}
          </td>

          <!-- Actions -->
          <td class="relative whitespace-nowrap py-5 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
            <div class="flex items-center justify-end space-x-2">
              <button
                @click="$emit('view', user)"
                class="p-1.5 rounded-lg text-slate-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-900/30 transition-all duration-200"
                title="Voir les détails"
              >
                <EyeIcon class="h-4 w-4" />
              </button>
              <button
                @click="$emit('edit', user)"
                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all duration-200"
                title="Modifier"
              >
                <PencilIcon class="h-4 w-4" />
              </button>
              <button
                @click="$emit('impersonate', user)"
                class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-all duration-200"
                title="Se connecter en tant que"
              >
                <UserIcon class="h-4 w-4" />
              </button>
              <button
                @click="$emit('delete', user)"
                class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition-all duration-200"
                title="Supprimer"
              >
                <TrashIcon class="h-4 w-4" />
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import {
  ChevronUpIcon,
  ChevronDownIcon,
  EyeIcon,
  PencilIcon,
  UserIcon,
  TrashIcon,
  UsersIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  users: {
    type: Array,
    default: () => []
  },
  selectedUsers: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
})

defineEmits(['select', 'select-all', 'view', 'edit', 'delete', 'impersonate'])

// Sorting
const sortBy = ref('name')
const sortOrder = ref('asc')

// Table columns
const columns = [
  { key: 'name', label: 'Utilisateur', sortable: true },
  { key: 'status', label: 'Statut', sortable: true },
  { key: 'role', label: 'Rôle', sortable: true },
  { key: 'company', label: 'Entreprise', sortable: true },
  { key: 'segment', label: 'Segment', sortable: false },
  { key: 'lastLoginAt', label: 'Dernière connexion', sortable: true },
  { key: 'createdAt', label: 'Inscription', sortable: true }
]

// Computed properties
const isAllSelected = computed(() => {
  return props.users.length > 0 && props.selectedUsers.length === props.users.length
})

const isIndeterminate = computed(() => {
  return props.selectedUsers.length > 0 && props.selectedUsers.length < props.users.length
})

const sortedUsers = computed(() => {
  if (!sortBy.value) return props.users

  return [...props.users].sort((a, b) => {
    let aValue = a[sortBy.value]
    let bValue = b[sortBy.value]

    // Handle nested properties
    if (sortBy.value === 'company') {
      aValue = a.company?.name || ''
      bValue = b.company?.name || ''
    }

    // Handle dates
    if (sortBy.value === 'createdAt' || sortBy.value === 'lastLoginAt') {
      aValue = aValue ? new Date(aValue) : new Date(0)
      bValue = bValue ? new Date(bValue) : new Date(0)
    }

    // Convert to string for comparison
    aValue = String(aValue).toLowerCase()
    bValue = String(bValue).toLowerCase()

    if (sortOrder.value === 'asc') {
      return aValue < bValue ? -1 : aValue > bValue ? 1 : 0
    } else {
      return aValue > bValue ? -1 : aValue < bValue ? 1 : 0
    }
  })
})

// Methods
function handleSort(column) {
  if (sortBy.value === column) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = column
    sortOrder.value = 'asc'
  }
}

function getStatusColor(status) {
  const colors = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-gray-100 text-gray-800',
    suspended: 'bg-red-100 text-red-800',
    pending: 'bg-yellow-100 text-yellow-800'
  }
  return colors[status] || 'bg-gray-100 text-gray-800'
}

function getStatusLabel(status) {
  const labels = {
    active: 'Actif',
    inactive: 'Inactif',
    suspended: 'Suspendu',
    pending: 'En attente'
  }
  return labels[status] || status
}

function getRoleColor(role) {
  const colors = {
    admin: 'bg-purple-100 text-purple-800',
    manager: 'bg-blue-100 text-blue-800',
    employee: 'bg-gray-100 text-gray-800',
    hr: 'bg-green-100 text-green-800'
  }
  return colors[role] || 'bg-gray-100 text-gray-800'
}

function getRoleLabel(role) {
  const labels = {
    admin: 'Administrateur',
    manager: 'Manager',
    employee: 'Employé',
    hr: 'RH'
  }
  return labels[role] || role
}

function getSegmentColor(segment) {
  const colors = {
    champions: 'bg-green-100 text-green-800',
    loyal: 'bg-blue-100 text-blue-800',
    potential: 'bg-yellow-100 text-yellow-800',
    new: 'bg-purple-100 text-purple-800',
    'at-risk': 'bg-red-100 text-red-800'
  }
  return colors[segment] || 'bg-gray-100 text-gray-800'
}

function getSegmentLabel(segment) {
  const labels = {
    champions: 'Champions',
    loyal: 'Loyaux',
    potential: 'Potentiels',
    new: 'Nouveaux',
    'at-risk': 'À risque'
  }
  return labels[segment] || segment
}

function formatLastLogin(date) {
  if (!date) return 'Jamais'

  const now = new Date()
  const loginDate = new Date(date)
  const diff = now - loginDate

  if (diff < 60000) return 'À l\'instant'
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h`
  if (diff < 604800000) return `${Math.floor(diff / 86400000)}j`

  return loginDate.toLocaleDateString('fr-FR')
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}
</script>