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
                {{ formattedValue }}
              </div>
              <div
                v-if="change !== undefined"
                :class="[
                  'ml-2 flex items-baseline text-sm font-semibold',
                  changeColor
                ]"
              >
                <component
                  :is="changeIcon"
                  class="self-center flex-shrink-0 h-4 w-4"
                />
                <span class="ml-1">
                  {{ Math.abs(change) }}
                </span>
              </div>
            </dd>
            <dd v-if="changeLabel" class="text-xs text-gray-500 mt-1">
              {{ changeLabel }}
            </dd>
          </dl>
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
  UsersIcon,
  BuildingOfficeIcon,
  CreditCardIcon,
  CurrencyEuroIcon,
  ChartBarIcon,
  ChatBubbleLeftRightIcon,
  ArrowUpIcon,
  ArrowDownIcon
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
    default: 'blue',
    validator: (value) => ['blue', 'green', 'purple', 'yellow', 'red'].includes(value)
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

// Color classes
const colorClasses = computed(() => {
  const colors = {
    blue: {
      bg: 'bg-blue-500',
      icon: 'text-white'
    },
    green: {
      bg: 'bg-green-500',
      icon: 'text-white'
    },
    purple: {
      bg: 'bg-purple-500',
      icon: 'text-white'
    },
    yellow: {
      bg: 'bg-yellow-500',
      icon: 'text-white'
    },
    red: {
      bg: 'bg-red-500',
      icon: 'text-white'
    }
  }
  return colors[props.color] || colors.blue
})

// Change indicator
const changeColor = computed(() => {
  if (props.change === undefined) return ''
  return props.change >= 0 ? 'text-green-600' : 'text-red-600'
})

const changeIcon = computed(() => {
  if (props.change === undefined) return null
  return props.change >= 0 ? ArrowUpIcon : ArrowDownIcon
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

  return props.value.toLocaleString('fr-FR')
})
</script>
