<template>
  <div class="space-y-4">
    <!-- Security Score -->
    <div class="text-center">
      <div class="relative inline-flex items-center justify-center w-20 h-20">
        <svg class="w-20 h-20 transform -rotate-90" viewBox="0 0 100 100">
          <circle
            cx="50"
            cy="50"
            r="40"
            stroke="#F3F4F6"
            stroke-width="8"
            fill="none"
          />
          <circle
            cx="50"
            cy="50"
            r="40"
            :stroke="scoreColor"
            stroke-width="8"
            fill="none"
            stroke-linecap="round"
            :stroke-dasharray="circumference"
            :stroke-dashoffset="strokeDashoffset"
            class="transition-all duration-1000 ease-out"
          />
        </svg>
        <div class="absolute inset-0 flex items-center justify-center">
          <div class="text-center">
            <div class="text-lg font-bold text-gray-900">{{ status.score }}</div>
            <div class="text-xs text-gray-500">/100</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Security Alerts -->
    <div class="border-t border-gray-200 pt-4">
      <h4 class="text-sm font-medium text-gray-900 mb-3">Alertes de sécurité</h4>
      <div class="space-y-2">
        <div 
          v-for="alert in alerts"
          :key="alert.id"
          :class="[
            'p-3 rounded-lg border-l-4',
            alert.severity === 'high' ? 'bg-red-50 border-red-400' :
            alert.severity === 'medium' ? 'bg-yellow-50 border-yellow-400' :
            'bg-blue-50 border-blue-400'
          ]"
        >
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <p 
                :class="[
                  'text-sm font-medium',
                  alert.severity === 'high' ? 'text-red-800' :
                  alert.severity === 'medium' ? 'text-yellow-800' :
                  'text-blue-800'
                ]"
              >
                {{ alert.message }}
              </p>
              <p class="text-xs text-gray-600 mt-1">{{ alert.details }}</p>
              <div class="flex items-center space-x-2 mt-2 text-xs">
                <span 
                  :class="[
                    'inline-flex items-center px-2 py-1 rounded-full font-medium',
                    alert.status === 'open' ? 'bg-red-100 text-red-800' :
                    alert.status === 'investigating' ? 'bg-yellow-100 text-yellow-800' :
                    'bg-green-100 text-green-800'
                  ]"
                >
                  {{ getStatusLabel(alert.status) }}
                </span>
                <span class="text-gray-500">{{ formatTime(alert.timestamp) }}</span>
              </div>
            </div>
            
            <div class="flex items-center space-x-2 ml-3">
              <button
                @click="$emit('investigate', alert)"
                class="text-xs text-indigo-600 hover:text-indigo-500 font-medium"
              >
                Enquêter
              </button>
              <button
                @click="$emit('dismiss', alert.id)"
                class="text-xs text-gray-400 hover:text-gray-600"
              >
                ✕
              </button>
            </div>
          </div>
        </div>
        
        <div v-if="alerts.length === 0" class="text-center py-4">
          <ShieldCheckIcon class="mx-auto h-8 w-8 text-green-400" />
          <p class="mt-2 text-sm text-gray-500">Aucune alerte de sécurité</p>
        </div>
      </div>
    </div>

    <!-- Security Recommendations -->
    <div class="border-t border-gray-200 pt-4">
      <h4 class="text-sm font-medium text-gray-900 mb-3">Recommandations</h4>
      <div class="space-y-2">
        <div class="flex items-start p-2 bg-blue-50 rounded-lg">
          <LightBulbIcon class="h-4 w-4 text-blue-400 mt-0.5 mr-2 flex-shrink-0" />
          <p class="text-xs text-blue-700">
            Activer l'authentification à deux facteurs pour tous les administrateurs
          </p>
        </div>
        <div class="flex items-start p-2 bg-blue-50 rounded-lg">
          <LightBulbIcon class="h-4 w-4 text-blue-400 mt-0.5 mr-2 flex-shrink-0" />
          <p class="text-xs text-blue-700">
            Mettre à jour les certificats SSL avant leur expiration
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  ShieldCheckIcon,
  LightBulbIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  alerts: {
    type: Array,
    default: () => []
  },
  status: {
    type: Object,
    required: true,
    default: () => ({
      level: 'high',
      label: 'Sécurisé',
      score: 95
    })
  }
})

defineEmits(['investigate', 'dismiss'])

// Circle calculations
const radius = 40
const circumference = computed(() => 2 * Math.PI * radius)
const strokeDashoffset = computed(() => {
  const progress = props.status.score / 100
  return circumference.value - (progress * circumference.value)
})

const scoreColor = computed(() => {
  const score = props.status.score
  if (score >= 80) return '#10B981' // green
  if (score >= 60) return '#F59E0B' // yellow
  return '#EF4444' // red
})

function getStatusLabel(status) {
  const labels = {
    open: 'Ouvert',
    investigating: 'En investigation',
    resolved: 'Résolu'
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
</script>