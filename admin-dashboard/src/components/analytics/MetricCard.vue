<template>
  <div class="bg-white overflow-hidden shadow rounded-lg">
    <div class="p-5">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <div
            :class="[
              'flex items-center justify-center h-8 w-8 rounded-md',
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
            <dt class="text-sm font-medium text-gray-500 truncate">
              {{ title }}
            </dt>
            <dd class="flex items-baseline">
              <div class="text-2xl font-semibold text-gray-900">
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
      <div v-if="showProgress" class="mt-4">
        <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
          <span>Progression</span>
          <span>{{ progressValue }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
          <div
            :class="[
              'h-2 rounded-full transition-all duration-500',
              colorClasses.progress
            ]"
            :style="{ width: `${progressValue}%` }"
          ></div>
        </div>
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="isLoading" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center">
      <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  TrendingUpIcon,
  TrendingDownIcon,
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
      bg: 'bg-blue-500',
      icon: 'text-white',
      progress: 'bg-blue-500'
    },
    green: {
      bg: 'bg-green-500',
      icon: 'text-white',
      progress: 'bg-green-500'
    },
    purple: {
      bg: 'bg-purple-500',
      icon: 'text-white',
      progress: 'bg-purple-500'
    },
    yellow: {
      bg: 'bg-yellow-500',
      icon: 'text-white',
      progress: 'bg-yellow-500'
    },
    red: {
      bg: 'bg-red-500',
      icon: 'text-white',
      progress: 'bg-red-500'
    },
    indigo: {
      bg: 'bg-indigo-500',
      icon: 'text-white',
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