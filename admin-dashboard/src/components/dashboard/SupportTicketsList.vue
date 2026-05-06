<template>
  <div class="flow-root">
    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
      <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5">
          <table class="min-w-full divide-y divide-gray-300">
            <thead class="bg-gray-50">
              <tr>
                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:pl-6">
                  Ticket
                </th>
                <th scope="col" class="px-3 py-3.5 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                  Priorité
                </th>
                <th scope="col" class="px-3 py-3.5 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                  Statut
                </th>
                <th scope="col" class="px-3 py-3.5 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                  Créé
                </th>
                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                  <span class="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
              <tr 
                v-for="ticket in tickets"
                :key="ticket.id"
                class="hover:bg-gray-50"
              >
                <td class="whitespace-nowrap py-4 pl-4 pr-3 sm:pl-6">
                  <div class="flex items-center">
                    <div class="flex-shrink-0">
                      <div 
                        :class="[
                          'h-8 w-8 rounded-full flex items-center justify-center',
                          getPriorityColor(ticket.priority)
                        ]"
                      >
                        <span class="text-xs font-medium text-white">
                          #{{ ticket.id }}
                        </span>
                      </div>
                    </div>
                    <div class="ml-4">
                      <div class="text-sm font-medium text-gray-900">
                        {{ ticket.subject }}
                      </div>
                      <div class="text-sm text-gray-500">
                        {{ ticket.user?.name || 'Utilisateur inconnu' }}
                      </div>
                    </div>
                  </div>
                </td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                  <span 
                    :class="[
                      'inline-flex rounded-full px-2 text-xs font-semibold leading-5',
                      getPriorityBadgeColor(ticket.priority)
                    ]"
                  >
                    {{ getPriorityLabel(ticket.priority) }}
                  </span>
                </td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                  <span 
                    :class="[
                      'inline-flex rounded-full px-2 text-xs font-semibold leading-5',
                      getStatusBadgeColor(ticket.status)
                    ]"
                  >
                    {{ getStatusLabel(ticket.status) }}
                  </span>
                </td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                  {{ formatTime(ticket.created_at) }}
                </td>
                <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                  <div class="flex items-center justify-end space-x-2">
                    <button
                      @click="viewTicket(ticket.id)"
                      class="text-indigo-600 hover:text-indigo-900"
                    >
                      Voir
                    </button>
                    <button
                      v-if="ticket.status === 'open'"
                      @click="assignTicket(ticket.id)"
                      class="text-green-600 hover:text-green-900"
                    >
                      Assigner
                    </button>
                    <button
                      v-if="ticket.status !== 'closed'"
                      @click="closeTicket(ticket.id)"
                      class="text-red-600 hover:text-red-900"
                    >
                      Fermer
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    
    <!-- Empty state -->
    <div v-if="tickets.length === 0" class="text-center py-12">
      <ChatBubbleLeftRightIcon class="mx-auto h-12 w-12 text-gray-400" />
      <h3 class="mt-2 text-sm font-medium text-gray-900">Aucun ticket en attente</h3>
      <p class="mt-1 text-sm text-gray-500">
        Tous les tickets de support ont été traités.
      </p>
    </div>
    
    <!-- View all link -->
    <div v-if="tickets.length > 0" class="mt-6 text-center">
      <router-link 
        to="/support"
        class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
      >
        Voir tous les tickets de support
        <span aria-hidden="true"> →</span>
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'

const router = useRouter()
const toast = useToast()

// Mock tickets data (in real app, this would come from an API)
const tickets = ref([
  {
    id: 1001,
    subject: 'Problème de connexion mobile',
    priority: 'high',
    status: 'open',
    user: { name: 'Marie Dubois', email: 'marie@example.com' },
    created_at: new Date(Date.now() - 3600000) // 1 hour ago
  },
  {
    id: 1002,
    subject: 'Erreur lors de l\'export des données',
    priority: 'medium',
    status: 'in_progress',
    user: { name: 'Jean Martin', email: 'jean@example.com' },
    created_at: new Date(Date.now() - 7200000) // 2 hours ago
  },
  {
    id: 1003,
    subject: 'Demande de nouvelle fonctionnalité',
    priority: 'low',
    status: 'open',
    user: { name: 'Sophie Laurent', email: 'sophie@example.com' },
    created_at: new Date(Date.now() - 10800000) // 3 hours ago
  },
  {
    id: 1004,
    subject: 'Bug dans le calcul des heures',
    priority: 'critical',
    status: 'open',
    user: { name: 'Pierre Durand', email: 'pierre@example.com' },
    created_at: new Date(Date.now() - 1800000) // 30 minutes ago
  }
])

onMounted(() => {
  // In a real app, load tickets from API
})

// Methods
function getPriorityColor(priority) {
  const colors = {
    critical: 'bg-red-600',
    high: 'bg-orange-500',
    medium: 'bg-yellow-500',
    low: 'bg-green-500'
  }
  return colors[priority] || 'bg-gray-500'
}

function getPriorityBadgeColor(priority) {
  const colors = {
    critical: 'bg-red-100 text-red-800',
    high: 'bg-orange-100 text-orange-800',
    medium: 'bg-yellow-100 text-yellow-800',
    low: 'bg-green-100 text-green-800'
  }
  return colors[priority] || 'bg-gray-100 text-gray-800'
}

function getPriorityLabel(priority) {
  const labels = {
    critical: 'Critique',
    high: 'Haute',
    medium: 'Moyenne',
    low: 'Basse'
  }
  return labels[priority] || priority
}

function getStatusBadgeColor(status) {
  const colors = {
    open: 'bg-blue-100 text-blue-800',
    in_progress: 'bg-yellow-100 text-yellow-800',
    resolved: 'bg-green-100 text-green-800',
    closed: 'bg-gray-100 text-gray-800'
  }
  return colors[status] || 'bg-gray-100 text-gray-800'
}

function getStatusLabel(status) {
  const labels = {
    open: 'Ouvert',
    in_progress: 'En cours',
    resolved: 'Résolu',
    closed: 'Fermé'
  }
  return labels[status] || status
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

function viewTicket(ticketId) {
  router.push(`/support/tickets/${ticketId}`)
}

async function assignTicket(ticketId) {
  try {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 500))
    
    // Update ticket status
    const ticket = tickets.value.find(t => t.id === ticketId)
    if (ticket) {
      ticket.status = 'in_progress'
    }
    
    toast.success(`Ticket #${ticketId} assigné`)
  } catch (error) {
    console.error('Failed to assign ticket:', error)
    toast.error('Erreur lors de l\'assignation du ticket')
  }
}

async function closeTicket(ticketId) {
  try {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 500))
    
    // Update ticket status
    const ticket = tickets.value.find(t => t.id === ticketId)
    if (ticket) {
      ticket.status = 'closed'
    }
    
    toast.success(`Ticket #${ticketId} fermé`)
  } catch (error) {
    console.error('Failed to close ticket:', error)
    toast.error('Erreur lors de la fermeture du ticket')
  }
}
</script>