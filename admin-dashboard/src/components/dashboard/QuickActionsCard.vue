<template>
  <div class="bg-white overflow-hidden shadow rounded-lg">
    <div class="p-6">
      <h3 class="text-lg font-medium text-gray-900 mb-4">Actions Rapides</h3>
      
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <!-- Create User -->
        <button
          @click="handleAction('create-user')"
          class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <UserPlusIcon class="h-8 w-8 text-blue-500 mb-2" />
          <span class="text-sm font-medium text-gray-900">Créer Utilisateur</span>
        </button>

        <!-- Create Company -->
        <button
          @click="handleAction('create-company')"
          class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <BuildingOfficeIcon class="h-8 w-8 text-green-500 mb-2" />
          <span class="text-sm font-medium text-gray-900">Créer Entreprise</span>
        </button>

        <!-- System Backup -->
        <button
          @click="handleAction('backup')"
          :disabled="isBackupRunning"
          class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <CloudArrowUpIcon 
            :class="[
              'h-8 w-8 text-purple-500 mb-2',
              isBackupRunning ? 'animate-pulse' : ''
            ]" 
          />
          <span class="text-sm font-medium text-gray-900">
            {{ isBackupRunning ? 'Sauvegarde...' : 'Sauvegarder' }}
          </span>
        </button>

        <!-- Send Notification -->
        <button
          @click="handleAction('send-notification')"
          class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <BellIcon class="h-8 w-8 text-yellow-500 mb-2" />
          <span class="text-sm font-medium text-gray-900">Notification</span>
        </button>

        <!-- Export Data -->
        <button
          @click="handleAction('export-data')"
          class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <DocumentArrowDownIcon class="h-8 w-8 text-indigo-500 mb-2" />
          <span class="text-sm font-medium text-gray-900">Exporter</span>
        </button>

        <!-- System Maintenance -->
        <button
          @click="handleAction('maintenance')"
          class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <WrenchScrewdriverIcon class="h-8 w-8 text-orange-500 mb-2" />
          <span class="text-sm font-medium text-gray-900">Maintenance</span>
        </button>

        <!-- View Logs -->
        <button
          @click="handleAction('view-logs')"
          class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <DocumentTextIcon class="h-8 w-8 text-gray-500 mb-2" />
          <span class="text-sm font-medium text-gray-900">Logs</span>
        </button>

        <!-- System Stats -->
        <button
          @click="handleAction('system-stats')"
          class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <ChartBarIcon class="h-8 w-8 text-teal-500 mb-2" />
          <span class="text-sm font-medium text-gray-900">Statistiques</span>
        </button>
      </div>

      <!-- Recent Actions -->
      <div class="mt-6 pt-4 border-t border-gray-200">
        <h4 class="text-sm font-medium text-gray-900 mb-3">Actions Récentes</h4>
        <div class="space-y-2">
          <div 
            v-for="action in recentActions"
            :key="action.id"
            class="flex items-center justify-between text-sm"
          >
            <div class="flex items-center">
              <div 
                :class="[
                  'h-2 w-2 rounded-full mr-3',
                  action.status === 'success' ? 'bg-green-400' :
                  action.status === 'error' ? 'bg-red-400' : 'bg-yellow-400'
                ]"
              ></div>
              <span class="text-gray-600">{{ action.description }}</span>
            </div>
            <span class="text-gray-400">{{ formatTime(action.timestamp) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Action Modal -->
    <ActionModal
      v-if="showModal"
      :action="selectedAction"
      @close="showModal = false"
      @confirm="executeAction"
    />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import {
  UserPlusIcon,
  BuildingOfficeIcon,
  CloudArrowUpIcon,
  BellIcon,
  DocumentArrowDownIcon,
  WrenchScrewdriverIcon,
  DocumentTextIcon,
  ChartBarIcon
} from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import ActionModal from '@/components/modals/ActionModal.vue'

const router = useRouter()
const toast = useToast()

const showModal = ref(false)
const selectedAction = ref(null)
const isBackupRunning = ref(false)

// Recent actions (mock data)
const recentActions = reactive([
  {
    id: 1,
    description: 'Sauvegarde système créée',
    status: 'success',
    timestamp: new Date(Date.now() - 300000) // 5 minutes ago
  },
  {
    id: 2,
    description: 'Utilisateur "John Doe" créé',
    status: 'success',
    timestamp: new Date(Date.now() - 900000) // 15 minutes ago
  },
  {
    id: 3,
    description: 'Export des données terminé',
    status: 'success',
    timestamp: new Date(Date.now() - 1800000) // 30 minutes ago
  }
])

// Action definitions
const actions = {
  'create-user': {
    title: 'Créer un Utilisateur',
    description: 'Créer un nouveau compte utilisateur administrateur',
    confirmText: 'Créer',
    route: '/users/create'
  },
  'create-company': {
    title: 'Créer une Entreprise',
    description: 'Ajouter une nouvelle entreprise au système',
    confirmText: 'Créer',
    route: '/companies/create'
  },
  'backup': {
    title: 'Sauvegarde Système',
    description: 'Créer une sauvegarde complète du système',
    confirmText: 'Sauvegarder',
    dangerous: false
  },
  'send-notification': {
    title: 'Envoyer une Notification',
    description: 'Envoyer une notification à tous les utilisateurs',
    confirmText: 'Envoyer',
    dangerous: true
  },
  'export-data': {
    title: 'Exporter les Données',
    description: 'Exporter les données du système au format CSV',
    confirmText: 'Exporter'
  },
  'maintenance': {
    title: 'Mode Maintenance',
    description: 'Activer le mode maintenance du système',
    confirmText: 'Activer',
    dangerous: true
  },
  'view-logs': {
    title: 'Consulter les Logs',
    description: 'Voir les logs système récents',
    route: '/logs'
  },
  'system-stats': {
    title: 'Statistiques Système',
    description: 'Voir les statistiques détaillées du système',
    route: '/system'
  }
}

// Methods
function handleAction(actionKey) {
  const action = actions[actionKey]
  if (!action) return

  // If action has a route, navigate directly
  if (action.route) {
    router.push(action.route)
    return
  }

  // Otherwise, show confirmation modal
  selectedAction.value = { key: actionKey, ...action }
  showModal.value = true
}

async function executeAction(actionKey) {
  showModal.value = false
  
  try {
    switch (actionKey) {
      case 'backup':
        await performBackup()
        break
      case 'send-notification':
        await sendNotification()
        break
      case 'export-data':
        await exportData()
        break
      case 'maintenance':
        await toggleMaintenance()
        break
      default:
        toast.info('Action non implémentée')
    }
  } catch (error) {
    console.error('Action failed:', error)
    toast.error('Erreur lors de l\'exécution de l\'action')
  }
}

async function performBackup() {
  isBackupRunning.value = true
  
  // Simulate backup process
  await new Promise(resolve => setTimeout(resolve, 3000))
  
  isBackupRunning.value = false
  toast.success('Sauvegarde créée avec succès')
  
  // Add to recent actions
  recentActions.unshift({
    id: Date.now(),
    description: 'Sauvegarde système créée',
    status: 'success',
    timestamp: new Date()
  })
}

async function sendNotification() {
  // Simulate API call
  await new Promise(resolve => setTimeout(resolve, 1000))
  
  toast.success('Notification envoyée à tous les utilisateurs')
  
  recentActions.unshift({
    id: Date.now(),
    description: 'Notification globale envoyée',
    status: 'success',
    timestamp: new Date()
  })
}

async function exportData() {
  // Simulate export
  await new Promise(resolve => setTimeout(resolve, 2000))
  
  // Create and download a mock CSV file
  const csvContent = "data:text/csv;charset=utf-8,Name,Email,Company\nJohn Doe,john@example.com,Acme Corp"
  const encodedUri = encodeURI(csvContent)
  const link = document.createElement("a")
  link.setAttribute("href", encodedUri)
  link.setAttribute("download", "export.csv")
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  
  toast.success('Export terminé')
  
  recentActions.unshift({
    id: Date.now(),
    description: 'Export des données terminé',
    status: 'success',
    timestamp: new Date()
  })
}

async function toggleMaintenance() {
  // Simulate API call
  await new Promise(resolve => setTimeout(resolve, 1000))
  
  toast.warning('Mode maintenance activé')
  
  recentActions.unshift({
    id: Date.now(),
    description: 'Mode maintenance activé',
    status: 'warning',
    timestamp: new Date()
  })
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
</script>