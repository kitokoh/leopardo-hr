<template>
  <div class="stat-card relative overflow-hidden group">
    <!-- Background Gradient for Premium feel -->
    <div :class="['absolute -right-4 -top-4 w-24 h-24 rounded-full blur-3xl opacity-20 transition-opacity group-hover:opacity-30', statusColor.glow]"></div>

    <div class="p-5 relative z-10">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <div
            :class="[
              'flex items-center justify-center h-10 w-10 rounded-xl shadow-lg transition-transform group-hover:scale-110',
              statusColor.bg
            ]"
          >
            <component
              :is="iconComponent"
              :class="['h-5 w-5', statusColor.icon]"
            />
          </div>
        </div>
        <div class="ml-5 w-0 flex-1">
          <dl>
            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400 truncate uppercase tracking-widest text-[10px] font-black">
              {{ title }}
            </dt>
            <dd class="flex items-center mt-1">
              <div class="flex items-center">
                <div
                  :class="[
                    'h-2 w-2 rounded-full mr-2 shadow-sm animate-pulse',
                    statusIndicatorColor
                  ]"
                ></div>
                <div :class="['text-base font-bold uppercase tracking-tight', statusTextColor]">
                  {{ statusLabel }}
                </div>
              </div>
            </dd>
            <dd v-if="details" class="text-xs text-gray-500 mt-1">
              {{ details }}
            </dd>
          </dl>
        </div>
      </div>

      <!-- Status details -->
      <div v-if="showDetails" class="mt-4 pt-4 border-t border-slate-200/50 dark:border-slate-800/50">
        <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">
          Sync: {{ formatTime(lastCheck) }}
        </div>
        <div v-if="uptime" class="text-xs text-gray-500 mt-1">
          Uptime: {{ uptime }}
        </div>
      </div>
    </div>

    <!-- Pulse animation for active status -->
    <div
      v-if="status === 'healthy'"
      class="absolute top-2 right-2"
    >
      <div class="flex h-3 w-3">
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
        <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  ServerIcon,
  CircleStackIcon,
  CloudIcon,
  WifiIcon,
  ExclamationTriangleIcon,
  XCircleIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  status: {
    type: String,
    required: true,
    validator: (value) => ['healthy', 'warning', 'error', 'maintenance'].includes(value)
  },
  details: {
    type: String,
    default: ''
  },
  icon: {
    type: String,
    default: 'ServerIcon'
  },
  showDetails: {
    type: Boolean,
    default: true
  },
  lastCheck: {
    type: Date,
    default: () => new Date()
  },
  uptime: {
    type: String,
    default: ''
  }
})

// Icon mapping
const iconMap = {
  ServerIcon,
  CircleStackIcon,
  CloudIcon,
  WifiIcon,
  ExclamationTriangleIcon,
  XCircleIcon
}

const iconComponent = computed(() => iconMap[props.icon] || ServerIcon)

// Status colors and labels
const statusColor = computed(() => {
  const colors = {
    healthy: {
      bg: 'bg-gradient-to-br from-emerald-500 to-emerald-600',
      icon: 'text-white',
      glow: 'bg-emerald-500'
    },
    warning: {
      bg: 'bg-gradient-to-br from-yellow-500 to-yellow-600',
      icon: 'text-white',
      glow: 'bg-yellow-500'
    },
    error: {
      bg: 'bg-gradient-to-br from-red-500 to-red-600',
      icon: 'text-white',
      glow: 'bg-red-500'
    },
    maintenance: {
      bg: 'bg-gradient-to-br from-slate-500 to-slate-600',
      icon: 'text-white',
      glow: 'bg-slate-500'
    }
  }
  return colors[props.status] || colors.healthy
})

const statusIndicatorColor = computed(() => {
  const colors = {
    healthy: 'bg-green-400',
    warning: 'bg-yellow-400',
    error: 'bg-red-400',
    maintenance: 'bg-gray-400'
  }
  return colors[props.status] || 'bg-gray-400'
})

const statusTextColor = computed(() => {
  const colors = {
    healthy: 'text-green-600',
    warning: 'text-yellow-600',
    error: 'text-red-600',
    maintenance: 'text-gray-600'
  }
  return colors[props.status] || 'text-gray-600'
})

const statusLabel = computed(() => {
  const labels = {
    healthy: 'Opérationnel',
    warning: 'Attention',
    error: 'Erreur',
    maintenance: 'Maintenance'
  }
  return labels[props.status] || 'Inconnu'
})

// Methods
function formatTime(date) {
  if (!date) return 'Jamais'

  const now = new Date()
  const diff = now - date

  if (diff < 60000) return 'À l\'instant'
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h`
  return date.toLocaleDateString('fr-FR')
}
</script>