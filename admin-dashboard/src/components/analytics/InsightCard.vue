<template>
  <div 
    :class="[
      'rounded-lg border-l-4 p-4',
      borderColor,
      backgroundColor
    ]"
  >
    <div class="flex items-start">
      <div class="flex-shrink-0">
        <component 
          :is="insightIcon" 
          :class="['h-5 w-5', iconColor]"
        />
      </div>
      
      <div class="ml-3 flex-1">
        <div class="flex items-center justify-between">
          <h4 :class="['text-sm font-medium', titleColor]">
            {{ insight.title }}
          </h4>
          <div class="flex items-center space-x-2">
            <!-- Impact badge -->
            <span 
              :class="[
                'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium',
                impactBadgeColor
              ]"
            >
              {{ impactLabel }}
            </span>
            
            <!-- Confidence score -->
            <div class="flex items-center text-xs text-gray-500">
              <span class="mr-1">Confiance:</span>
              <div class="flex items-center">
                <div class="w-8 bg-gray-200 rounded-full h-1 mr-1">
                  <div 
                    :class="[
                      'h-1 rounded-full',
                      confidenceColor
                    ]"
                    :style="{ width: `${insight.confidence * 100}%` }"
                  ></div>
                </div>
                <span class="font-medium">{{ Math.round(insight.confidence * 100) }}%</span>
              </div>
            </div>
          </div>
        </div>
        
        <p :class="['mt-1 text-sm', textColor]">
          {{ insight.description }}
        </p>
        
        <!-- Action button -->
        <div class="mt-3 flex items-center justify-between">
          <button
            @click="$emit('action', insight)"
            :class="[
              'inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2',
              actionButtonColor
            ]"
          >
            {{ insight.action }}
            <ArrowRightIcon class="ml-1 h-3 w-3" />
          </button>
          
          <!-- Additional actions -->
          <div class="flex items-center space-x-2">
            <button
              @click="$emit('dismiss', insight.id)"
              class="text-xs text-gray-400 hover:text-gray-600"
            >
              Ignorer
            </button>
            <button
              @click="$emit('save', insight.id)"
              class="text-xs text-gray-400 hover:text-gray-600"
            >
              Sauvegarder
            </button>
          </div>
        </div>
        
        <!-- Metadata -->
        <div class="mt-2 flex items-center text-xs text-gray-400 space-x-4">
          <span>Généré par IA</span>
          <span>{{ formatDate(insight.createdAt || new Date()) }}</span>
          <span v-if="insight.category">{{ insight.category }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  LightBulbIcon,
  ExclamationTriangleIcon,
  CheckCircleIcon,
  InformationCircleIcon,
  ArrowRightIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  insight: {
    type: Object,
    required: true,
    validator: (insight) => {
      return insight.type && insight.title && insight.description && insight.action
    }
  }
})

defineEmits(['action', 'dismiss', 'save'])

// Computed properties based on insight type
const insightIcon = computed(() => {
  const iconMap = {
    opportunity: LightBulbIcon,
    warning: ExclamationTriangleIcon,
    success: CheckCircleIcon,
    info: InformationCircleIcon
  }
  return iconMap[props.insight.type] || InformationCircleIcon
})

const borderColor = computed(() => {
  const colorMap = {
    opportunity: 'border-blue-400',
    warning: 'border-yellow-400',
    success: 'border-green-400',
    info: 'border-gray-400'
  }
  return colorMap[props.insight.type] || 'border-gray-400'
})

const backgroundColor = computed(() => {
  const colorMap = {
    opportunity: 'bg-blue-50',
    warning: 'bg-yellow-50',
    success: 'bg-green-50',
    info: 'bg-gray-50'
  }
  return colorMap[props.insight.type] || 'bg-gray-50'
})

const iconColor = computed(() => {
  const colorMap = {
    opportunity: 'text-blue-400',
    warning: 'text-yellow-400',
    success: 'text-green-400',
    info: 'text-gray-400'
  }
  return colorMap[props.insight.type] || 'text-gray-400'
})

const titleColor = computed(() => {
  const colorMap = {
    opportunity: 'text-blue-800',
    warning: 'text-yellow-800',
    success: 'text-green-800',
    info: 'text-gray-800'
  }
  return colorMap[props.insight.type] || 'text-gray-800'
})

const textColor = computed(() => {
  const colorMap = {
    opportunity: 'text-blue-700',
    warning: 'text-yellow-700',
    success: 'text-green-700',
    info: 'text-gray-700'
  }
  return colorMap[props.insight.type] || 'text-gray-700'
})

const actionButtonColor = computed(() => {
  const colorMap = {
    opportunity: 'text-blue-700 bg-blue-100 hover:bg-blue-200 focus:ring-blue-500',
    warning: 'text-yellow-700 bg-yellow-100 hover:bg-yellow-200 focus:ring-yellow-500',
    success: 'text-green-700 bg-green-100 hover:bg-green-200 focus:ring-green-500',
    info: 'text-gray-700 bg-gray-100 hover:bg-gray-200 focus:ring-gray-500'
  }
  return colorMap[props.insight.type] || 'text-gray-700 bg-gray-100 hover:bg-gray-200 focus:ring-gray-500'
})

const impactLabel = computed(() => {
  const labelMap = {
    high: 'Impact élevé',
    medium: 'Impact moyen',
    low: 'Impact faible',
    positive: 'Positif'
  }
  return labelMap[props.insight.impact] || props.insight.impact
})

const impactBadgeColor = computed(() => {
  const colorMap = {
    high: 'bg-red-100 text-red-800',
    medium: 'bg-yellow-100 text-yellow-800',
    low: 'bg-gray-100 text-gray-800',
    positive: 'bg-green-100 text-green-800'
  }
  return colorMap[props.insight.impact] || 'bg-gray-100 text-gray-800'
})

const confidenceColor = computed(() => {
  const confidence = props.insight.confidence || 0
  if (confidence >= 0.8) return 'bg-green-500'
  if (confidence >= 0.6) return 'bg-yellow-500'
  return 'bg-red-500'
})

// Methods
function formatDate(date) {
  return new Date(date).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>