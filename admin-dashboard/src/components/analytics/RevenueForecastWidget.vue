<template>
  <div class="space-y-4">
    <!-- Forecast Value -->
    <div class="text-center">
      <div class="text-3xl font-bold text-gray-900">
        {{ formattedRevenue }}
      </div>
      <div class="text-sm text-gray-500">Prévision mois prochain</div>
    </div>

    <!-- Confidence Level -->
    <div>
      <div class="flex items-center justify-between text-sm mb-2">
        <span class="text-gray-600">Niveau de confiance</span>
        <span class="font-medium text-gray-900">{{ Math.round(data.confidence * 100) }}%</span>
      </div>
      <div class="w-full bg-gray-200 rounded-full h-2">
        <div
          :class="[
            'h-2 rounded-full transition-all duration-1000',
            confidenceColor
          ]"
          :style="{ width: `${data.confidence * 100}%` }"
        ></div>
      </div>
    </div>

    <!-- Trend Indicator -->
    <div class="flex items-center justify-center space-x-2">
      <component
        :is="trendIcon"
        :class="['h-5 w-5', trendColor]"
      />
      <span :class="['text-sm font-medium', trendColor]">
        {{ trendLabel }}
      </span>
    </div>

    <!-- Mini Chart -->
    <div class="h-20">
      <canvas ref="chartCanvas" class="w-full h-full"></canvas>
    </div>

    <!-- Breakdown -->
    <div class="space-y-2 pt-3 border-t border-gray-200">
      <div class="flex items-center justify-between text-sm">
        <span class="text-gray-600">Revenus récurrents</span>
        <span class="font-medium">{{ formatCurrency(data.nextMonth * 0.8) }}</span>
      </div>
      <div class="flex items-center justify-between text-sm">
        <span class="text-gray-600">Nouveaux clients</span>
        <span class="font-medium">{{ formatCurrency(data.nextMonth * 0.15) }}</span>
      </div>
      <div class="flex items-center justify-between text-sm">
        <span class="text-gray-600">Upsells</span>
        <span class="font-medium">{{ formatCurrency(data.nextMonth * 0.05) }}</span>
      </div>
    </div>

    <!-- Range -->
    <div class="bg-gray-50 rounded-lg p-3">
      <div class="text-xs text-gray-500 mb-1">Fourchette de prévision</div>
      <div class="flex items-center justify-between">
        <div class="text-sm">
          <span class="text-gray-600">Min:</span>
          <span class="font-medium ml-1">{{ formatCurrency(data.nextMonth * 0.85) }}</span>
        </div>
        <div class="text-sm">
          <span class="text-gray-600">Max:</span>
          <span class="font-medium ml-1">{{ formatCurrency(data.nextMonth * 1.15) }}</span>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="space-y-2">
      <button
        @click="$emit('view-details')"
        class="w-full px-3 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 rounded-md hover:bg-indigo-100"
      >
        Voir détails de la prévision
      </button>
      <button
        @click="$emit('export-forecast')"
        class="w-full px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
      >
        Exporter prévision
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, nextTick } from 'vue'
import {
  ArrowTrendingUpIcon as TrendingUpIcon,
  ArrowTrendingDownIcon as TrendingDownIcon,
  ArrowRightIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  data: {
    type: Object,
    required: true,
    default: () => ({
      nextMonth: 45000,
      confidence: 0.85,
      trend: 'positive'
    })
  }
})

defineEmits(['view-details', 'export-forecast'])

const chartCanvas = ref(null)

// Computed properties
const formattedRevenue = computed(() => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0
  }).format(props.data.nextMonth)
})

const confidenceColor = computed(() => {
  const confidence = props.data.confidence
  if (confidence >= 0.8) return 'bg-green-500'
  if (confidence >= 0.6) return 'bg-yellow-500'
  return 'bg-red-500'
})

const trendIcon = computed(() => {
  switch (props.data.trend) {
    case 'positive': return TrendingUpIcon
    case 'negative': return TrendingDownIcon
    default: return ArrowRightIcon
  }
})

const trendColor = computed(() => {
  switch (props.data.trend) {
    case 'positive': return 'text-green-600'
    case 'negative': return 'text-red-600'
    default: return 'text-gray-600'
  }
})

const trendLabel = computed(() => {
  switch (props.data.trend) {
    case 'positive': return 'Tendance positive'
    case 'negative': return 'Tendance négative'
    default: return 'Tendance stable'
  }
})

// Methods
function formatCurrency(amount) {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0
  }).format(amount)
}

function drawMiniChart() {
  if (!chartCanvas.value) return

  const canvas = chartCanvas.value
  const ctx = canvas.getContext('2d')
  const { width, height } = canvas.getBoundingClientRect()

  // Set canvas size
  canvas.width = width * window.devicePixelRatio
  canvas.height = height * window.devicePixelRatio
  ctx.scale(window.devicePixelRatio, window.devicePixelRatio)

  // Generate sample data for the last 6 months + forecast
  const data = []
  const baseRevenue = props.data.nextMonth * 0.8

  for (let i = 6; i >= 0; i--) {
    const variation = (Math.random() - 0.5) * 0.2
    const value = baseRevenue * (1 + variation + (6 - i) * 0.05)
    data.push(value)
  }

  // Add forecast point
  data.push(props.data.nextMonth)

  // Draw chart
  const padding = 10
  const chartWidth = width - padding * 2
  const chartHeight = height - padding * 2

  const minValue = Math.min(...data) * 0.95
  const maxValue = Math.max(...data) * 1.05
  const valueRange = maxValue - minValue

  // Draw historical line
  ctx.beginPath()
  ctx.strokeStyle = '#3B82F6'
  ctx.lineWidth = 2

  for (let i = 0; i < data.length - 1; i++) {
    const x = padding + (i / (data.length - 1)) * chartWidth
    const y = padding + chartHeight - ((data[i] - minValue) / valueRange) * chartHeight

    if (i === 0) {
      ctx.moveTo(x, y)
    } else {
      ctx.lineTo(x, y)
    }
  }
  ctx.stroke()

  // Draw forecast line (dashed)
  ctx.beginPath()
  ctx.setLineDash([5, 5])
  ctx.strokeStyle = '#10B981'
  ctx.lineWidth = 2

  const lastHistoricalX = padding + ((data.length - 2) / (data.length - 1)) * chartWidth
  const lastHistoricalY = padding + chartHeight - ((data[data.length - 2] - minValue) / valueRange) * chartHeight
  const forecastX = padding + ((data.length - 1) / (data.length - 1)) * chartWidth
  const forecastY = padding + chartHeight - ((data[data.length - 1] - minValue) / valueRange) * chartHeight

  ctx.moveTo(lastHistoricalX, lastHistoricalY)
  ctx.lineTo(forecastX, forecastY)
  ctx.stroke()

  // Reset line dash
  ctx.setLineDash([])

  // Draw forecast point
  ctx.beginPath()
  ctx.fillStyle = '#10B981'
  ctx.arc(forecastX, forecastY, 4, 0, 2 * Math.PI)
  ctx.fill()
}

onMounted(async () => {
  await nextTick()
  setTimeout(drawMiniChart, 100)
})
</script>
