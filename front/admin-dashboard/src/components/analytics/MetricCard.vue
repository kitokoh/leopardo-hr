<template>
  <div class="stat-card relative overflow-hidden group">
    <!-- Background Gradient for Premium feel -->
    <div :class="['absolute -right-4 -top-4 w-24 h-24 rounded-full blur-3xl opacity-20 transition-opacity group-hover:opacity-30', colorClasses.glow]"></div>

    <div class="relative z-10">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <div
            :class="[
              'flex items-center justify-center h-10 w-10 rounded-xl shadow-lg transition-transform group-hover:scale-110',
              colorClasses.bg
            ]"
          >
            <component
              :is="iconComponent"
              :class="['h-5 w-5', colorClasses.icon]"
            />
          </div>
        </div>
        <div class="ml-5 w-0 flex-1">
          <dl>
            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400 truncate">
              {{ title }}
            </dt>
            <dd class="flex items-baseline">
              <div class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                {{ prefix }}{{ formattedValue }}{{ suffix }}
              </div>
              <div
                v-if="trend"
                :class="[
                  'ml-2 flex items-baseline text-sm font-semibold',
                  trendColor
                ]"
              >
                <component
                  :is="trendIcon"
                  class="self-center flex-shrink-0 h-4 w-4"
                />
                <span class="ml-1">
                  {{ trendValue }}%
                </span>
              </div>
            </dd>
            <dd v-if="subtitle" class="text-xs text-gray-500 mt-1">
              {{ subtitle }}
            </dd>
          </dl>
        </div>
      </div>

      <!-- Mini chart or progress bar -->
      <div v-if="showProgress" class="mt-4 animate-slide-up">
        <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1.5">
          <span>Progression</span>
          <span>{{ progressValue }}%</span>
        </div>
        <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
          <div
            :class="[
              'h-full rounded-full transition-all duration-1000 ease-out',
              colorClasses.progress
            ]"
            :style="{ width: `${progressValue}%` }"
          ></div>
        </div>
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="isLoading" class="absolute inset-0 bg-white/50 dark:bg-slate-950/50 backdrop-blur-sm flex items-center justify-center z-20">
      <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-brand-600"></div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  ArrowTrendingUpIcon as TrendingUpIcon,
  ArrowTrendingDownIcon as TrendingDownIcon,
  ArrowUpIcon,
  ArrowDownIcon,
  ChartBarIcon,
  CurrencyEuroIcon,
  UserPlusIcon,
  UsersIcon
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
  prefix: {
    type: String,
    default: ''
  },
  suffix: {
    type: String,
    default: ''
  },
  subtitle: {
    type: String,
    default: ''
  },
  trend: {
    type: String,
    default: null,
    validator: (value) => !value || ['up', 'down', 'stable'].includes(value)
  },
  trendValue: {
    type: Number,
    default: 0
  },
  icon: {
    type: String,
    default: 'ChartBarIcon'
  },
  color: {
    type: String,
    default: 'blue',
    validator: (value) => ['blue', 'green', 'purple', 'yellow', 'red', 'indigo'].includes(value)
  },
  showProgress: {
    type: Boolean,
    default: false
  },
  progressValue: {
    type: Number,
    default: 0
  },
  isLoading: {
    type: Boolean,
    default: false
  }
})

// Icon mapping
const iconMap = {
  TrendingUpIcon,
  TrendingDownIcon,
  ChartBarIcon,
  CurrencyEuroIcon,
  UserPlusIcon,
  UsersIcon
}

const iconComponent = computed(() => iconMap[props.icon] || ChartBarIcon)

// Color classes
const colorClasses = computed(() => {
  const colors = {
    blue: {
      bg: 'bg-gradient-to-br from-blue-500 to-blue-600',
      icon: 'text-white',
      glow: 'bg-blue-500',
      progress: 'bg-blue-500'
    },
    green: {
      bg: 'bg-gradient-to-br from-emerald-500 to-emerald-600',
      icon: 'text-white',
      glow: 'bg-emerald-500',
      progress: 'bg-emerald-500'
    },
    purple: {
      bg: 'bg-gradient-to-br from-brand-500 to-brand-600',
      icon: 'text-white',
      glow: 'bg-brand-500',
      progress: 'bg-brand-500'
    },
    yellow: {
      bg: 'bg-gradient-to-br from-yellow-500 to-yellow-600',
      icon: 'text-white',
      glow: 'bg-yellow-500',
      progress: 'bg-yellow-500'
    },
    red: {
      bg: 'bg-gradient-to-br from-red-500 to-red-600',
      icon: 'text-white',
      glow: 'bg-red-500',
      progress: 'bg-red-500'
    },
    indigo: {
      bg: 'bg-gradient-to-br from-indigo-500 to-indigo-600',
      icon: 'text-white',
      glow: 'bg-indigo-500',
      progress: 'bg-indigo-500'
    }
  }
  return colors[props.color] || colors.blue
})

// Trend styling
const trendColor = computed(() => {
  if (!props.trend) return ''
  return props.trend === 'up' ? 'text-green-600' :
         props.trend === 'down' ? 'text-red-600' : 'text-gray-600'
})

const trendIcon = computed(() => {
  if (!props.trend) return null
  return props.trend === 'up' ? ArrowUpIcon :
         props.trend === 'down' ? ArrowDownIcon : null
})

// Format value
const formattedValue = computed(() => {
  if (typeof props.value === 'string') {
    return props.value
  }

  // Format numbers with locale
  if (props.value >= 1000000) {
    return (props.value / 1000000).toFixed(1) + 'M'
  } else if (props.value >= 1000) {
    return (props.value / 1000).toFixed(1) + 'K'
  }

  // Handle decimals
  if (props.value % 1 !== 0) {
    return props.value.toFixed(1)
  }

  return props.value.toLocaleString('fr-FR')
})
</script>
