<template>
  <div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        @click="$emit('close')"
      ></div>

      <!-- Modal panel -->
      <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:align-middle">
        <div class="bg-white">
          <!-- Header -->
          <div class="border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-4">
                <img
                  :src="user?.avatar"
                  :alt="user?.name"
                  class="h-16 w-16 rounded-full"
                />
                <div>
                  <h3 class="text-xl font-semibold text-gray-900">{{ user?.name }}</h3>
                  <p class="text-sm text-gray-500">{{ user?.email }}</p>
                  <div class="flex items-center space-x-2 mt-1">
                    <span
                      :class="[
                        'inline-flex rounded-full px-2 text-xs font-semibold leading-5',
                        getStatusColor(user?.status)
                      ]"
                    >
                      {{ getStatusLabel(user?.status) }}
                    </span>
                    <span
                      :class="[
                        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                        getRoleColor(user?.role)
                      ]"
                    >
                      {{ getRoleLabel(user?.role) }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="flex items-center space-x-3">
                <button
                  @click="$emit('edit', user)"
                  class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                >
                  <PencilIcon class="h-4 w-4 mr-2" />
                  Modifier
                </button>
                <button
                  @click="$emit('close')"
                  class="rounded-md bg-white text-gray-400 hover:text-gray-500"
                >
                  <XMarkIcon class="h-6 w-6" />
                </button>
              </div>
            </div>
          </div>

          <!-- Content -->
          <div class="px-6 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
              <!-- Main Info -->
              <div class="lg:col-span-2 space-y-6">
                <!-- Basic Information -->
                <div class="bg-gray-50 rounded-lg p-4">
                  <h4 class="text-lg font-medium text-gray-900 mb-4">Informations générales</h4>
                  <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                      <dt class="text-sm font-medium text-gray-500">ID Utilisateur</dt>
                      <dd class="mt-1 text-sm text-gray-900">#{{ user?.id }}</dd>
                    </div>
                    <div>
                      <dt class="text-sm font-medium text-gray-500">Entreprise</dt>
                      <dd class="mt-1 text-sm text-gray-900">{{ user?.company?.name || 'Aucune' }}</dd>
                    </div>
                    <div>
                      <dt class="text-sm font-medium text-gray-500">Segment</dt>
                      <dd class="mt-1">
                        <span
                          :class="[
                            'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium',
                            getSegmentColor(user?.segment)
                          ]"
                        >
                          {{ getSegmentLabel(user?.segment) }}
                        </span>
                      </dd>
                    </div>
                    <div>
                      <dt class="text-sm font-medium text-gray-500">Date d'inscription</dt>
                      <dd class="mt-1 text-sm text-gray-900">{{ formatDate(user?.createdAt) }}</dd>
                    </div>
                    <div>
                      <dt class="text-sm font-medium text-gray-500">Dernière connexion</dt>
                      <dd class="mt-1 text-sm text-gray-900">{{ formatLastLogin(user?.lastLoginAt) }}</dd>
                    </div>
                    <div>
                      <dt class="text-sm font-medium text-gray-500">Dernière mise à jour</dt>
                      <dd class="mt-1 text-sm text-gray-900">{{ formatDate(user?.updatedAt || user?.createdAt) }}</dd>
                    </div>
                  </dl>
                </div>

                <!-- Activity Stats -->
                <div class="bg-gray-50 rounded-lg p-4">
                  <h4 class="text-lg font-medium text-gray-900 mb-4">Statistiques d'activité</h4>
                  <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="text-center">
                      <div class="text-2xl font-bold text-indigo-600">{{ userStats.loginCount }}</div>
                      <div class="text-sm text-gray-500">Connexions</div>
                    </div>
                    <div class="text-center">
                      <div class="text-2xl font-bold text-green-600">{{ userStats.activeHours }}</div>
                      <div class="text-sm text-gray-500">Heures actives</div>
                    </div>
                    <div class="text-center">
                      <div class="text-2xl font-bold text-blue-600">{{ userStats.featuresUsed }}</div>
                      <div class="text-sm text-gray-500">Fonctionnalités</div>
                    </div>
                    <div class="text-center">
                      <div class="text-2xl font-bold text-purple-600">{{ userStats.supportTickets }}</div>
                      <div class="text-sm text-gray-500">Tickets support</div>
                    </div>
                  </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-gray-50 rounded-lg p-4">
                  <h4 class="text-lg font-medium text-gray-900 mb-4">Activité récente</h4>
                  <div class="flow-root">
                    <ul role="list" class="-mb-8">
                      <li
                        v-for="(activity, activityIdx) in recentActivity"
                        :key="activity.id"
                      >
                        <div class="relative pb-8">
                          <span
                            v-if="activityIdx !== recentActivity.length - 1"
                            class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"
                          ></span>
                          <div class="relative flex space-x-3">
                            <div>
                              <span
                                :class="[
                                  'h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white',
                                  getActivityColor(activity.type)
                                ]"
                              >
                                <component
                                  :is="getActivityIcon(activity.type)"
                                  class="h-4 w-4 text-white"
                                />
                              </span>
                            </div>
                            <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                              <div>
                                <p class="text-sm text-gray-500">{{ activity.description }}</p>
                              </div>
                              <div class="whitespace-nowrap text-right text-sm text-gray-500">
                                {{ formatTime(activity.timestamp) }}
                              </div>
                            </div>
                          </div>
                        </div>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Sidebar -->
              <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                  <h4 class="text-lg font-medium text-gray-900 mb-4">Actions rapides</h4>
                  <div class="space-y-3">
                    <button
                      @click="impersonateUser"
                      class="w-full flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-gray-50 rounded-md hover:bg-gray-100"
                    >
                      <UserIcon class="h-4 w-4 mr-2" />
                      Se connecter en tant que
                    </button>
                    <button
                      @click="resetPassword"
                      class="w-full flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-gray-50 rounded-md hover:bg-gray-100"
                    >
                      <KeyIcon class="h-4 w-4 mr-2" />
                      Réinitialiser mot de passe
                    </button>
                    <button
                      @click="sendMessage"
                      class="w-full flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-gray-50 rounded-md hover:bg-gray-100"
                    >
                      <ChatBubbleLeftRightIcon class="h-4 w-4 mr-2" />
                      Envoyer un message
                    </button>
                    <button
                      @click="viewAuditLog"
                      class="w-full flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-gray-50 rounded-md hover:bg-gray-100"
                    >
                      <DocumentTextIcon class="h-4 w-4 mr-2" />
                      Journal d'audit
                    </button>
                  </div>
                </div>

                <!-- Permissions -->
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                  <h4 class="text-lg font-medium text-gray-900 mb-4">Permissions</h4>
                  <div class="space-y-2">
                    <div
                      v-for="permission in userPermissions"
                      :key="permission.name"
                      class="flex items-center justify-between"
                    >
                      <span class="text-sm text-gray-600">{{ permission.name }}</span>
                      <span
                        :class="[
                          'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium',
                          permission.granted ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        ]"
                      >
                        {{ permission.granted ? 'Accordée' : 'Refusée' }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Security -->
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                  <h4 class="text-lg font-medium text-gray-900 mb-4">Sécurité</h4>
                  <div class="space-y-3">
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-600">2FA activé</span>
                      <span
                        :class="[
                          'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium',
                          userSecurity.twoFactorEnabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        ]"
                      >
                        {{ userSecurity.twoFactorEnabled ? 'Oui' : 'Non' }}
                      </span>
                    </div>
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-600">Sessions actives</span>
                      <span class="text-sm font-medium text-gray-900">{{ userSecurity.activeSessions }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-600">Dernière IP</span>
                      <span class="text-sm font-medium text-gray-900">{{ userSecurity.lastIpAddress }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import {
  PencilIcon,
  XMarkIcon,
  UserIcon,
  KeyIcon,
  ChatBubbleLeftRightIcon,
  DocumentTextIcon,
  LoginIcon,
  CogIcon,
  ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'

const props = defineProps({
  user: {
    type: Object,
    required: true
  }
})

defineEmits(['close', 'edit'])

const toast = useToast()

// Mock data
const userStats = reactive({
  loginCount: 142,
  activeHours: 89,
  featuresUsed: 12,
  supportTickets: 3
})

const userSecurity = reactive({
  twoFactorEnabled: true,
  activeSessions: 2,
  lastIpAddress: '192.168.1.100'
})

const userPermissions = ref([
  { name: 'Voir tableau de bord', granted: true },
  { name: 'Gérer équipe', granted: true },
  { name: 'Accès rapports', granted: true },
  { name: 'Configuration système', granted: false },
  { name: 'Gestion utilisateurs', granted: false }
])

const recentActivity = ref([])

onMounted(() => {
  loadUserActivity()
})

// Methods
function loadUserActivity() {
  // Mock recent activity
  recentActivity.value = [
    {
      id: 1,
      type: 'login',
      description: 'Connexion depuis l\'application mobile',
      timestamp: new Date(Date.now() - 3600000)
    },
    {
      id: 2,
      type: 'feature_use',
      description: 'Utilisation du module de pointage',
      timestamp: new Date(Date.now() - 7200000)
    },
    {
      id: 3,
      type: 'settings',
      description: 'Modification des préférences de notification',
      timestamp: new Date(Date.now() - 86400000)
    },
    {
      id: 4,
      type: 'support',
      description: 'Création d\'un ticket de support',
      timestamp: new Date(Date.now() - 172800000)
    }
  ]
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

function getActivityColor(type) {
  const colors = {
    login: 'bg-green-500',
    feature_use: 'bg-blue-500',
    settings: 'bg-gray-500',
    support: 'bg-yellow-500'
  }
  return colors[type] || 'bg-gray-400'
}

function getActivityIcon(type) {
  const icons = {
    login: LoginIcon,
    feature_use: CogIcon,
    settings: CogIcon,
    support: ExclamationTriangleIcon
  }
  return icons[type] || CogIcon
}

function formatDate(date) {
  if (!date) return 'Jamais'
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatLastLogin(date) {
  if (!date) return 'Jamais connecté'

  const now = new Date()
  const loginDate = new Date(date)
  const diff = now - loginDate

  if (diff < 60000) return 'À l\'instant'
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h`
  if (diff < 604800000) return `${Math.floor(diff / 86400000)}j`

  return loginDate.toLocaleDateString('fr-FR')
}

function formatTime(timestamp) {
  const now = new Date()
  const time = new Date(timestamp)
  const diff = now - time

  if (diff < 60000) return 'À l\'instant'
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h`
  return time.toLocaleDateString('fr-FR')
}

// Action methods
function impersonateUser() {
  toast.info(`Connexion en tant que ${props.user.name}`)
}

function resetPassword() {
  toast.success('Email de réinitialisation envoyé')
}

function sendMessage() {
  toast.info('Ouverture de la messagerie')
}

function viewAuditLog() {
  toast.info('Ouverture du journal d\'audit')
}
</script>