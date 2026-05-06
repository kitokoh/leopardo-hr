<template>
  <div class="overflow-hidden">
    <table class="min-w-full divide-y divide-gray-300">
      <thead class="bg-gray-50">
        <tr>
          <th scope="col" class="relative w-12 px-6 sm:w-16 sm:px-8">
            <input
              type="checkbox"
              :checked="isAllSelected"
              :indeterminate="isIndeterminate"
              @change="$emit('select-all', $event.target.checked)"
              class="absolute left-4 top-1/2 -mt-2 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
            />
          </th>
          <th
            v-for="column in columns"
            :key="column.key"
            scope="col"
            :class="[
              'px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider',
              column.sortable ? 'cursor-pointer hover:bg-gray-100' : ''
            ]"
            @click="column.sortable && handleSort(column.key)"
          >
            <div class="flex items-center space-x-1">
              <span>{{ column.label }}</span>
              <div v-if="column.sortable" class="flex flex-col">
                <ChevronUpIcon
                  :class="[
                    'h-3 w-3',
                    sortBy === column.key && sortOrder === 'asc' ? 'text-indigo-600' : 'text-gray-400'
                  ]"
                />
                <ChevronDownIcon
                  :class="[
                    'h-3 w-3 -mt-1',
                    sortBy === column.key && sortOrder === 'desc' ? 'text-indigo-600' : 'text-gray-400'
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
      <tbody class="divide-y divide-gray-200 bg-white">
        <!-- Loading state -->
        <tr v-if="loading">
          <td colspan="8" class="px-6 py-12 text-center">
            <div class="flex items-center justify-center">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
              <span class="ml-3 text-gray-500">Chargement des utilisateurs...</span>
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
            'hover:bg-gray-50',
            selectedUsers.includes(user.id) ? 'bg-indigo-50' : ''
          ]"
        >
          <td class="relative w-12 px-6 sm:w-16 sm:px-8">
            <input
              type="checkbox"
              :checked="selectedUsers.includes(user.id)"
              @change="$emit('select', user.id, $event.target.checked)"
              class="absolute left-4 top-1/2 -mt-2 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
            />
          </td>

          <!-- Avatar & Name -->
          <td class="whitespace-nowrap px-6 py-4">
            <div class="flex items-center">
              <div class="h-10 w-10 flex-shrink-0">
                <img
                  :src="user.avatar"
                  :alt="user.name"
                  class="h-10 w-10 rounded-full"
                />
              </div>
              <div class="ml-4">
                <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
                <div class="text-sm text-gray-500">{{ user.email }}</div>
              </div>
            </div>
          </td>

          <!-- Status -->
          <td class="whitespace-nowrap px-6 py-4">
            <span
              :class="[
                'inline-flex rounded-full px-2 text-xs font-semibold leading-5',
                getStatusColor(user.status)
              ]"
            >
              {{ getStatusLabel(user.status) }}
            </span>
          </td>

          <!-- Role -->
          <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
            <span
              :class="[
                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                getRoleColor(user.role)
              ]"
            >
              {{ getRoleLabel(user.role) }}
            </span>
          </td>

          <!-- Company -->
          <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
            {{ user.company?.name || '-' }}
          </td>

          <!-- Segment -->
          <td class="whitespace-nowrap px-6 py-4">
            <span
              :class="[
                'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium',
                getSegmentColor(user.segment)
              ]"
            >
              {{ getSegmentLabel(user.segment) }}
            </span>
          </td>

          <!-- Last Login -->
          <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
            {{ formatLastLogin(user.lastLoginAt) }}
          </td>

          <!-- Created At -->
          <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
            {{ formatDate(user.createdAt) }}
          </td>

          <!-- Actions -->
          <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
            <div class="flex items-center justify-end space-x-2">
              <button
                @click="$emit('view', user)"
                class="text-indigo-600 hover:text-indigo-900"
                title="Voir les détails"
              >
                <EyeIcon class="h-4 w-4" />
              </button>
              <button
                @click="$emit('edit', user)"
                class="text-gray-600 hover:text-gray-900"
                title="Modifier"
              >
                <PencilIcon class="h-4 w-4" />
              </button>
              <button
                @click="$emit('impersonate', user)"
                class="text-blue-600 hover:text-blue-900"
                title="Se connecter en tant que"
              >
                <UserIcon class="h-4 w-4" />
              </button>
              <button
                @click="$emit('delete', user)"
                class="text-red-600 hover:text-red-900"
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