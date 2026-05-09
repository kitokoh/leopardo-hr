<template>
  <div class="stat-card group">
    <!-- Decorative background element -->
    <div
      :class="[
        'absolute -right-4 -top-4 h-24 w-24 rounded-full opacity-10 transition-transform duration-500 group-hover:scale-150',
        colorClasses.bg
      ]"
    ></div>

    <div class="relative flex items-start justify-between">
      <div class="flex-1">
        <p class="text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">
          {{ title }}
        </p>

        <div class="flex items-baseline gap-2">
          <h3 class="text-2xl font-bold text-zinc-900 tracking-tight">
            {{ formattedValue }}
          </h3>

          <div
            v-if="change !== undefined"
            :class="[
              'flex items-center text-xs font-bold px-1.5 py-0.5 rounded-lg transition-colors',
              change >= 0 ? 'text-emerald-700 bg-emerald-50' : 'text-rose-700 bg-rose-50'
            ]"
          >
            <component
              :is="changeIcon"
              class="h-3 w-3 mr-0.5"
            />
            <span>{{ Math.abs(change) }}%</span>
          </div>
        </div>

        <p v-if="changeLabel" class="text-[10px] font-medium text-zinc-400 mt-1.5 flex items-center">
          <ClockIcon class="h-3 w-3 mr-1" />
          {{ changeLabel }}
        </p>
      </div>

      <div
        :class="[
          'flex h-12 w-12 items-center justify-center rounded-2xl shadow-lg transition-all duration-300 group-hover:scale-110 group-hover:-rotate-6',
          colorClasses.gradient
        ]"
      >
        <component
          :is="iconComponent"
          class="h-6 w-6 text-white"
        />
      </div>
    </div>

    <!-- Progress bar if needed (visual fluff) -->
    <div class="mt-5 h-1 w-full bg-zinc-100 rounded-full overflow-hidden">
      <div
        :class="['h-full transition-all duration-1000 ease-out rounded-full', colorClasses.bg]"
        :style="{ width: isLoading ? '30%' : '100%' }"
      ></div>
    </div>

    <!-- Loading overlay -->
    <div v-if="isLoading" class="absolute inset-0 bg-white/60 backdrop-blur-[1px] flex items-center justify-center transition-opacity duration-300">
      <div class="flex gap-1">
        <div class="h-1.5 w-1.5 bg-brand-500 rounded-full animate-bounce" style="animation-delay: 0s"></div>
        <div class="h-1.5 w-1.5 bg-brand-500 rounded-full animate-bounce" style="animation-delay: 0.15s"></div>
        <div class="h-1.5 w-1.5 bg-brand-500 rounded-full animate-bounce" style="animation-delay: 0.3s"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  UsersIcon,
  BuildingOfficeIcon,
  CreditCardIcon,
  CurrencyEuroIcon,
  ChartBarIcon,
  ChatBubbleLeftRightIcon,
  ArrowUpIcon,
  ArrowDownIcon,
  ClockIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  value: {
    type: [String, Number],
    required: true
  },
  change: {
    type: Number,
    default: undefined
  },
  changeLabel: {
    type: String,
    default: ''
  },
  icon: {
    type: String,
    default: 'ChartBarIcon'
  },
  color: {
    type: String,
    default: 'brand',
    validator: (value) => ['brand', 'blue', 'emerald', 'amber', 'rose', 'purple'].includes(value)
  },
  isLoading: {
    type: Boolean,
    default: false
  }
})

// Icon mapping
const iconMap = {
  UsersIcon,
  BuildingOfficeIcon,
  CreditCardIcon,
  CurrencyEuroIcon,
  ChartBarIcon,
  ChatBubbleLeftRightIcon
}

const iconComponent = computed(() => iconMap[props.icon] || ChartBarIcon)

// Color classes mapping to our new theme
const colorClasses = computed(() => {
  const configs = {
    brand: {
      bg: 'bg-brand-500',
      gradient: 'brand-gradient shadow-brand-200/50'
    },
    blue: {
      bg: 'bg-blue-500',
      gradient: 'bg-gradient-to-br from-blue-600 to-blue-400 shadow-blue-200/50'
    },
    emerald: {
      bg: 'bg-emerald-500',
      gradient: 'bg-gradient-to-br from-emerald-600 to-emerald-400 shadow-emerald-200/50'
    },
    amber: {
      bg: 'bg-amber-500',
      gradient: 'bg-gradient-to-br from-amber-600 to-amber-400 shadow-amber-200/50'
    },
    rose: {
      bg: 'bg-rose-500',
      gradient: 'bg-gradient-to-br from-rose-600 to-rose-400 shadow-rose-200/50'
    },
    purple: {
      bg: 'bg-purple-500',
      gradient: 'bg-gradient-to-br from-purple-600 to-purple-400 shadow-purple-200/50'
    }
  }
  return configs[props.color] || configs.brand
})

const changeIcon = computed(() => {
  if (props.change === undefined) return null
  return props.change >= 0 ? ArrowUpIcon : ArrowDownIcon
})

// Advanced formatting
const formattedValue = computed(() => {
  if (typeof props.value === 'string') return props.value

  const num = Number(props.value)
  if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M'
  if (num >= 1000) return (num / 1000).toFixed(1) + 'K'

  return num.toLocaleString('fr-FR')
})
</script>
