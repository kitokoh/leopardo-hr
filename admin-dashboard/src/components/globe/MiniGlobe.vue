<template>
  <div class="relative h-full w-full">
    <div
      ref="globeContainer"
      class="h-full w-full"
    ></div>

    <!-- Loading overlay -->
    <div
      v-if="isLoading"
      class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-75"
    >
      <div class="text-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
        <p class="mt-2 text-sm text-gray-500">Chargement du globe...</p>
      </div>
    </div>

    <!-- Controls -->
    <div class="absolute top-4 right-4 space-y-2">
      <button
        @click="toggleAutoRotate"
        :class="[
          'p-2 rounded-md shadow-sm text-sm font-medium',
          autoRotate
            ? 'bg-indigo-600 text-white hover:bg-indigo-700'
            : 'bg-white text-gray-700 hover:bg-gray-50'
        ]"
      >
        {{ autoRotate ? 'Arrêter' : 'Rotation' }}
      </button>

      <button
        @click="resetView"
        class="block p-2 rounded-md shadow-sm text-sm font-medium bg-white text-gray-700 hover:bg-gray-50"
      >
        Reset
      </button>
    </div>

    <!-- Stats overlay -->
    <div class="absolute bottom-4 left-4 bg-white bg-opacity-90 rounded-lg p-3 shadow-sm">
      <div class="text-xs text-gray-500 mb-1">Activité en temps réel</div>
      <div class="flex items-center space-x-4 text-sm">
        <div class="flex items-center">
          <div class="h-2 w-2 rounded-full bg-green-400 mr-2"></div>
          <span class="font-medium">{{ activePoints.length }}</span>
          <span class="text-gray-500 ml-1">connexions</span>
        </div>
        <div class="flex items-center">
          <div class="h-2 w-2 rounded-full bg-blue-400 mr-2"></div>
          <span class="font-medium">{{ countries.size }}</span>
          <span class="text-gray-500 ml-1">pays</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { useRealtimeStore } from '@/stores/realtime'

const globeContainer = ref(null)
const globe = ref(null)
const isLoading = ref(true)
const autoRotate = ref(true)
const activePoints = ref([])
const countries = ref(new Set())

const realtimeStore = useRealtimeStore()

onMounted(async () => {
  await nextTick()
  await initGlobe()

  // Listen for new globe points
  realtimeStore.$subscribe((mutation, state) => {
    if (mutation.events?.some(event => event.key === 'globePoints')) {
      updateGlobePoints(state.globePoints)
    }
  })

  // Initial points
  updateGlobePoints(realtimeStore.globePoints)
})

onUnmounted(() => {
  if (globe.value) {
    // Cleanup globe instance
    globe.value = null
  }
})

async function initGlobe() {
  try {
    // Dynamically import Globe.gl to avoid SSR issues
    const Globe = (await import('globe.gl')).default

    if (!globeContainer.value) return

    globe.value = Globe()(globeContainer.value)
      .globeImageUrl('//unpkg.com/three-globe/example/img/earth-night.jpg')
      .backgroundImageUrl('//unpkg.com/three-globe/example/img/night-sky.png')
      .pointOfView({ altitude: 2.5 })
      .enablePointerInteraction(true)

    // Configure points
    globe.value
      .pointsData([])
      .pointAltitude(0.1)
      .pointRadius(0.8)
      .pointColor(() => '#3B82F6')
      .pointLabel(d => `
        <div class="bg-white p-2 rounded shadow-lg text-sm">
          <div class="font-medium">${d.city}, ${d.country}</div>
          <div class="text-gray-500">${d.users} utilisateur(s)</div>
          <div class="text-xs text-gray-400">${new Date(d.timestamp).toLocaleString('fr-FR')}</div>
        </div>
      `)

    // Auto-rotate
    if (autoRotate.value) {
      globe.value.controls().autoRotate = true
      globe.value.controls().autoRotateSpeed = 0.5
    }

    isLoading.value = false
  } catch (error) {
    console.error('Failed to initialize globe:', error)
    isLoading.value = false
  }
}

function updateGlobePoints(points) {
  if (!globe.value || !points) return

  // Process points for globe
  const globePoints = points.map(point => ({
    lat: point.latitude,
    lng: point.longitude,
    city: point.city || 'Ville inconnue',
    country: point.country || 'Pays inconnu',
    users: point.users || 1,
    timestamp: point.timestamp || new Date()
  }))

  // Update active points and countries
  activePoints.value = globePoints
  countries.value = new Set(globePoints.map(p => p.country))

  // Update globe
  globe.value.pointsData(globePoints)
}

function toggleAutoRotate() {
  autoRotate.value = !autoRotate.value

  if (globe.value) {
    globe.value.controls().autoRotate = autoRotate.value
    if (autoRotate.value) {
      globe.value.controls().autoRotateSpeed = 0.5
    }
  }
}

function resetView() {
  if (globe.value) {
    globe.value.pointOfView({ altitude: 2.5 }, 1000)
  }
}

// Simulate some initial activity points
function generateMockPoints() {
  const cities = [
    { city: 'Paris', country: 'France', lat: 48.8566, lng: 2.3522 },
    { city: 'London', country: 'UK', lat: 51.5074, lng: -0.1278 },
    { city: 'New York', country: 'USA', lat: 40.7128, lng: -74.0060 },
    { city: 'Tokyo', country: 'Japan', lat: 35.6762, lng: 139.6503 },
    { city: 'Sydney', country: 'Australia', lat: -33.8688, lng: 151.2093 },
    { city: 'São Paulo', country: 'Brazil', lat: -23.5505, lng: -46.6333 },
    { city: 'Mumbai', country: 'India', lat: 19.0760, lng: 72.8777 },
    { city: 'Cairo', country: 'Egypt', lat: 30.0444, lng: 31.2357 }
  ]

  const points = []
  for (let i = 0; i < 15; i++) {
    const city = cities[Math.floor(Math.random() * cities.length)]
    points.push({
      ...city,
      latitude: city.lat + (Math.random() - 0.5) * 2, // Add some variation
      longitude: city.lng + (Math.random() - 0.5) * 2,
      users: Math.floor(Math.random() * 10) + 1,
      timestamp: new Date(Date.now() - Math.random() * 3600000) // Last hour
    })
  }

  return points
}

// Initialize with mock data if no real-time data
setTimeout(() => {
  if (activePoints.value.length === 0) {
    const mockPoints = generateMockPoints()
    realtimeStore.globePoints = mockPoints
    updateGlobePoints(mockPoints)
  }
}, 2000)
</script>