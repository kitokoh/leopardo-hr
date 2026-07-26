<template>
  <div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <div
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        @click="$emit('close')"
      ></div>

      <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:align-middle">
        <div class="border-b border-gray-200 px-6 py-4">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">{{ detail?.plate_number || 'Vehicule' }}</h3>
              <p class="text-sm text-gray-500">{{ detail?.brand }} {{ detail?.model }}</p>
            </div>
            <button class="rounded-md bg-white text-gray-400 hover:text-gray-500" @click="$emit('close')">
              <XMarkIcon class="h-6 w-6" />
            </button>
          </div>
        </div>

        <div class="px-6 py-6">
          <div v-if="loading" class="py-8 text-center text-sm text-gray-500">
            Chargement...
          </div>
          <div v-else-if="error" class="py-8 text-center text-sm text-red-600">
            {{ error }}
          </div>
          <div v-else class="space-y-6">
            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="text-sm font-medium text-gray-900 mb-3">Informations vehicule</h4>
              <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <dt class="text-xs font-medium text-gray-500">Immatriculation</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ detail?.plate_number || '\u2014' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Type</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ detail?.type || '\u2014' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Annee</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ detail?.year || '\u2014' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Carburant</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ detail?.fuel_type || '\u2014' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">VIN</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ detail?.vin || '\u2014' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Kilometrage</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ detail?.mileage ?? '\u2014' }} km</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Statut</dt>
                  <dd class="mt-1">
                    <StatusBadge :status="detail?.status" :map="vehicleStatusMap" />
                  </dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Conducteur assigne</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ detail?.assigned_to || detail?.assigned_driver_id || 'Aucun' }}</dd>
                </div>
              </dl>
            </div>

            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="text-sm font-medium text-gray-900 mb-3">Echeances</h4>
              <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <dt class="text-xs font-medium text-gray-500">Assurance</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ formatDate(detail?.insurance_expiry) }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Controle technique</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ formatDate(detail?.technical_control_expiry) }}</dd>
                </div>
              </dl>
            </div>

            <div v-if="alerts.length" class="bg-gray-50 rounded-lg p-4">
              <h4 class="text-sm font-medium text-gray-900 mb-3">Alertes recentes</h4>
              <ul class="space-y-2">
                <li v-for="alert in alerts" :key="alert.id" class="flex items-center justify-between text-sm">
                  <span class="text-gray-700">{{ alert.message }}</span>
                  <StatusBadge :status="alert.severity" :map="severityMap" />
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import StatusBadge from '@/components/common/StatusBadge.vue'

const props = defineProps({
  vehicleId: {
    type: [Number, String],
    required: true,
  },
})

defineEmits(['close'])

const loading = ref(true)
const error = ref('')
const detail = ref(null)
const alerts = ref([])

const vehicleStatusMap = {
  active: { label: 'En service', color: 'green' },
  maintenance: { label: 'Maintenance', color: 'yellow' },
  inactive: { label: 'Inactif', color: 'gray' },
  decommissioned: { label: 'Reforme', color: 'red' },
}

const severityMap = {
  low: { label: 'Faible', color: 'blue' },
  medium: { label: 'Moyen', color: 'yellow' },
  high: { label: 'Eleve', color: 'red' },
  critical: { label: 'Critique', color: 'red' },
}

function formatDate(date) {
  if (!date) return '\u2014'
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

async function fetchDetail() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get(`/v1/vehicles/${props.vehicleId}`)
    detail.value = res.data?.data || res.data

    try {
      const alertsRes = await api.get(`/v1/vehicles/${props.vehicleId}/alerts`)
      alerts.value = alertsRes.data?.data || alertsRes.data || []
    } catch {
      alerts.value = []
    }
  } catch {
    error.value = 'Impossible de charger les details du vehicule.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchDetail)
</script>
