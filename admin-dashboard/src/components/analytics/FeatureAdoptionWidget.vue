<template>
  <div class="space-y-4">
    <!-- Feature list with adoption rates -->
    <div class="space-y-3">
      <div 
        v-for="feature in features"
        :key="feature.name"
        class="flex items-center justify-between"
      >
        <div class="flex-1 min-w-0">
          <div class="flex items-center">
            <div 
              :class="[
                'w-3 h-3 rounded-full mr-3',
                getAdoptionColor(feature.adoption)
              ]"
            ></div>
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-900 truncate">
                {{ feature.name }}
              </p>
              <p class="text-xs text-gray-500">
                {{ feature.users }} utilisateurs
              </p>
            </div>
          </div>
        </div>
        
        <div class="ml-4 flex items-center space-x-2">
          <div class="text-right">
            <div class="text-sm font-medium text-gray-900">
              {{ Math.round(feature.adoption * 100) }}%
            </div>
            <div 
              :class="[
                'text-xs',
                feature.trend === 'up' ? 'text-green-600' : 
                feature.trend === 'down' ? 'text-red-600' : 'text-gray-500'
              ]"
            >
              {{ feature.trend === 'up' ? '↗' : feature.trend === 'down' ? '↘' : '→' }}
              {{ Math.abs(feature.change) }}%
            </div>
          </div>
          
          <!-- Progress bar -->
          <div class="w-16">
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div 
                :class="[
                  'h-2 rounded-full transition-all duration-500',
                  getAdoptionProgressColor(feature.adoption)
                ]"
                :style="{ width: `${feature.adoption * 100}%` }"
              ></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary stats -->
    <div class="pt-4 border-t border-gray-200">
      <div class="grid grid-cols-2 gap-4 text-center">
        <div>
          <div class="text-lg font-semibold text-gray-900">
            {{ averageAdoption }}%
          </div>
          <div class="text-xs text-gray-500">Adoption moyenne</div>
        </div>
        <div>
          <div class="text-lg font-semibold text-gray-900">
            {{ topFeatures.length }}
          </div>
          <div class="text-xs text-gray-500">Fonctionnalités populaires</div>
        </div>
      </div>
    </div>

    <!-- Top performing features -->
    <div class="bg-green-50 rounded-lg p-3">
      <h4 class="text-sm font-medium text-green-800 mb-2">
        🚀 Meilleures performances
      </h4>
      <div class="space-y-1">
        <div 
          v-for="feature in topFeatures.slice(0, 2)"
          :key="feature.name"
          class="text-sm text-green-700"
        >
          <span class="font-medium">{{ feature.name }}</span>
          <span class="ml-2">{{ Math.round(feature.adoption * 100) }}%</span>
        </div>
      </div>
    </div>

    <!-- Low adoption features -->
    <div v-if="lowAdoptionFeatures.length > 0" class="bg-yellow-50 rounded-lg p-3">
      <h4 class="text-sm font-medium text-yellow-800 mb-2">
        ⚠️ Adoption faible
      </h4>
      <div class="space-y-1">
        <div 
          v-for="feature in lowAdoptionFeatures.slice(0, 2)"
          :key="feature.name"
          class="text-sm text-yellow-700"
        >
          <span class="font-medium">{{ feature.name }}</span>
          <span class="ml-2">{{ Math.round(feature.adoption * 100) }}%</span>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="space-y-2">
      <button
        @click="$emit('analyze-features')"
        class="w-full px-3 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 rounded-md hover:bg-indigo-100"
      >
        Analyser en détail
      </button>
      <button
        @click="$emit('create-campaign')"
        class="w-full px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
      >
        Campagne d'adoption
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  data: {
    type: Array,
    default: () => [
      { name: 'Pointage mobile', adoption: 0.85, users: 1240, trend: 'up', change: 12 },
      { name: 'Rapports automatiques', adoption: 0.72, users: 980, trend: 'up', change: 8 },
      { name: 'Gestion des congés', adoption: 0.68, users: 890, trend: 'stable', change: 2 },
      { name: 'Planning équipes', adoption: 0.45, users: 620, trend: 'up', change: 15 },
      { name: 'Évaluations', adoption: 0.32, users: 450, trend: 'down', change: -5 },
      { name: 'Formation en ligne', adoption: 0.28, users: 380, trend: 'up', change: 18 },
      { name: 'Chat interne', adoption: 0.15, users: 210, trend: 'down', change: -8 }
    ]
  }
})

defineEmits(['analyze-features', 'create-campaign'])

// Computed properties
const features = computed(() => {
  return props.data.sort((a, b) => b.adoption - a.adoption)
})

const averageAdoption = computed(() => {
  const total = features.value.reduce((sum, feature) => sum + feature.adoption, 0)
  return Math.round((total / features.value.length) * 100)
})

const topFeatures = computed(() => {
  return features.value.filter(feature => feature.adoption >= 0.6)
})

const lowAdoptionFeatures = computed(() => {
  return features.value.filter(feature => feature.adoption < 0.3)
})

// Methods
function getAdoptionColor(adoption) {
  if (adoption >= 0.7) return 'bg-green-400'
  if (adoption >= 0.5) return 'bg-yellow-400'
  if (adoption >= 0.3) return 'bg-orange-400'
  return 'bg-red-400'
}

function getAdoptionProgressColor(adoption) {
  if (adoption >= 0.7) return 'bg-green-500'
  if (adoption >= 0.5) return 'bg-yellow-500'
  if (adoption >= 0.3) return 'bg-orange-500'
  return 'bg-red-500'
}
</script>