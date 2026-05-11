<template>
  <div class="relative h-full min-h-[320px] w-full overflow-hidden bg-slate-950">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(59,130,246,0.28),transparent_58%)]"></div>

    <div class="absolute inset-0 flex items-center justify-center">
      <div class="relative h-72 w-72 rounded-full border border-blue-300/30 bg-blue-500/10 shadow-[0_0_80px_rgba(59,130,246,0.35)]">
        <div class="absolute inset-6 rounded-full border border-emerald-300/20"></div>
        <div class="absolute inset-14 rounded-full border border-indigo-300/20"></div>
        <div
          v-for="point in activePoints"
          :key="point.id"
          class="absolute h-3 w-3 rounded-full bg-emerald-300 shadow-[0_0_18px_rgba(110,231,183,0.9)]"
          :style="{ left: `${point.x}%`, top: `${point.y}%` }"
          :title="`${point.city}, ${point.country}`"
        ></div>
      </div>
    </div>

    <div class="absolute right-4 top-4">
      <button
        class="rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
        @click="refreshPoints"
      >
        Actualiser
      </button>
    </div>

    <div class="absolute bottom-4 left-4 rounded-lg bg-white/90 p-3 shadow-sm">
      <div class="mb-1 text-xs text-gray-500">Activite en temps reel</div>
      <div class="flex items-center space-x-4 text-sm">
        <div class="flex items-center">
          <div class="mr-2 h-2 w-2 rounded-full bg-green-400"></div>
          <span class="font-medium">{{ activePoints.length }}</span>
          <span class="ml-1 text-gray-500">connexions</span>
        </div>
        <div class="flex items-center">
          <div class="mr-2 h-2 w-2 rounded-full bg-blue-400"></div>
          <span class="font-medium">{{ countries.size }}</span>
          <span class="ml-1 text-gray-500">pays</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRealtimeStore } from '@/stores/realtime'

const realtimeStore = useRealtimeStore()
const fallbackPoints = ref([
  { id: 1, city: 'Paris', country: 'France', x: 48, y: 35 },
  { id: 2, city: 'Istanbul', country: 'Turkiye', x: 58, y: 42 },
  { id: 3, city: 'Casablanca', country: 'Maroc', x: 43, y: 52 },
])

const activePoints = computed(() => {
  const points = realtimeStore.globePoints?.length ? realtimeStore.globePoints : fallbackPoints.value
  return points.map((point, index) => ({
    id: point.id ?? `${point.city}-${index}`,
    city: point.city ?? 'Ville',
    country: point.country ?? 'Pays',
    x: point.x ?? 28 + ((index * 17) % 48),
    y: point.y ?? 24 + ((index * 23) % 50),
  }))
})

const countries = computed(() => new Set(activePoints.value.map(point => point.country)))

function refreshPoints() {
  fallbackPoints.value = fallbackPoints.value.map((point, index) => ({
    ...point,
    x: 25 + ((point.x + 13 + index * 5) % 52),
    y: 22 + ((point.y + 9 + index * 7) % 54),
  }))
}
</script>
