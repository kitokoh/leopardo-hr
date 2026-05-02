<template>
  <div class="space-y-4">
    <!-- Risk Score -->
    <div class="text-center">
      <div class="relative inline-flex items-center justify-center w-24 h-24">
        <svg class="w-24 h-24 transform -rotate-90" viewBox="0 0 100 100">
          <!-- Background circle -->
          <circle
            cx="50"
            cy="50"
            r="40"
            stroke="#F3F4F6"
            stroke-width="8"
            fill="none"
          />
          <!-- Progress circle -->
          <circle
            cx="50"
            cy="50"
            r="40"
            :stroke="riskColor"
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
            <div :class="['text-lg font-bold', riskTextColor]">
              {{ Math.round(data.probability * 100) }}%
            </div>
            <div class="text-xs text-gray-500">Risque</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Risk Level -->
    <div class="text-center">
      <span 
        :class="[
          'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium',
          riskBadgeColor
        ]"
      >
        <component :is="riskIcon" class="w-4 h-4 mr-1" />
        {{ riskLevel }}
      </span>
    </div>

    <!-- Risk Factors -->
    <div>
      <h4 class="text-sm font-medium text-gray-900 mb-2">Facteurs de risque</h4>
      <div class="space-y-2">
        <div 
          v-for="(factor, index) in data.factors"
          :key="index"
          class="flex items-center text-sm"
        >
          <div 
            :class="[
              'w-2 h-2 rounded-full mr-3',
              getFactorColor(index)
            ]"
          ></div>
          <span class="text-gray-600">{{ factor }}</span>
        </div>
      </div>
    </div>

    <!-- At-Risk Users -->
    <div class="pt-3 border-t border-gray-200">
      <div class="flex items-center justify-between">
        <span class="text-sm text-gray-600">Utilisateurs à risque</span>
        <span class="text-sm font-medium text-gray-900">{{ data.riskUsers }}</span>
      </div>
      <div class="mt-2">
        <button
          @click="$emit('view-users')"
          class="w-full text-sm text-indigo-600 hover:text-indigo-500 font-medium"
        >
          Voir la liste →
        </button>
      </div>
    </div>

    <!-- Actions -->
    <div class="space-y-2">
      <button
        @click="$emit('create-campaign')"
        class="w-full px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
      >
        Créer campagne de rétention
      </button>
      <button
        @click="$emit('export-list')"
        class="w-full px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
      >
        Exporter la liste
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  ExclamationTriangleIcon,
  ShieldCheckIcon,
  ShieldExclamationIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  data: {
    type: Object,
    required: true,
    default: () => ({
      probability: 0.15,
      riskUsers: 23,
      factors: []
    })
  }
})

defineEmits(['view-users', 'create-campaign', 'export-list'])

// Circle calculations
const radius = 40
const circumference = computed(() => 2 * Math.PI * radius)
const strokeDashoffset = computed(() => {
  const progress = props.data.probability
  return circumference.value - (progress * circumference.value)
})

// Risk level calculations
const riskLevel = computed(() => {
  const probability = props.data.probability
  if (probability < 0.1) return 'Faible'
  if (probability < 0.25) return 'Modéré'
  if (probability < 0.5) return 'Élevé'
  return 'Critique'
})

const riskColor = computed(() => {
  const probability = props.data.probability
  if (probability < 0.1) return '#10B981' // green
  if (probability < 0.25) return '#F59E0B' // yellow
  if (probability < 0.5) return '#EF4444' // red
  return '#DC2626' // dark red
})

const riskTextColor = computed(() => {
  const probability = props.data.probability
  if (probability < 0.1) return 'text-green-600'
  if (probability < 0.25) return 'text-yellow-600'
  if (probability < 0.5) return 'text-red-600'
  return 'text-red-700'
})

const riskBadgeColor = computed(() => {
  const probability = props.data.probability
  if (probability < 0.1) return 'bg-green-100 text-green-800'
  if (probability < 0.25) return 'bg-yellow-100 text-yellow-800'
  if (probability < 0.5) return 'bg-red-100 text-red-800'
  return 'bg-red-200 text-red-900'
})

const riskIcon = computed(() => {
  const probability = props.data.probability
  if (probability < 0.1) return ShieldCheckIcon
  if (probability < 0.25) return ShieldExclamationIcon
  return ExclamationTriangleIcon
})

function getFactorColor(index) {
  const colors = [
    'bg-red-400',
    'bg-orange-400',
    'bg-yellow-400',
    'bg-blue-400',
    'bg-purple-400'
  ]
  return colors[index % colors.length]
}
</script>