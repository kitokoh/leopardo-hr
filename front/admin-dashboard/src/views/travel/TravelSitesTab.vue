<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-end gap-4">
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          {{ t('travel.sites.title', 'Sites touristiques') }}
        </h2>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
          {{ t('travel.sites.subtitle', 'Lieux d’intérêt avec localisation et recherche par ville.') }}
        </p>
      </div>
      <div class="ml-auto w-full sm:w-72">
        <FormField
          :id="'travel-sites-city-filter'"
          :label="t('travel.sites.filterCity', 'Filtrer par ville')"
        >
          <select v-model="cityFilter" class="form-input" @change="refreshKey += 1">
            <option value="">{{ t('travel.sites.allCities', 'Toutes les villes') }}</option>
            <option v-for="opt in cityOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </FormField>
      </div>
    </div>

    <TravelCrudSection
      :key="`sites-${refreshKey}`"
      :config="siteConfig"
      :lookups="{ cities: cityOptions }"
      :extra-params="extraParams"
      :column-display="siteDisplay"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import FormField from '@/components/common/FormField.vue'
import TravelCrudSection from '@/components/travel/TravelCrudSection.vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const cities = ref([])
const cityFilter = ref('')
const refreshKey = ref(0)

const cityOptions = computed(() =>
  cities.value.map((c) => ({ value: c.id, label: `${c.name}${c.country_iso2 ? ` (${c.country_iso2})` : ''}` }))
)

async function loadCities() {
  try {
    const res = await api.get('/travel/cities', { params: { per_page: 100 }, _skipAuthRedirect: true })
    cities.value = res.data?.data || []
  } catch {
    cities.value = []
  }
}

const extraParams = () => (cityFilter.value ? { city_id: cityFilter.value } : {})

const statusMap = {
  active: { labelKey: 'travel.status.active', color: 'green' },
  disabled: { labelKey: 'travel.sites.statusDisabled', color: 'gray' }
}

const siteConfig = computed(() => ({
  resource: 'tourist-sites',
  titleKey: 'travel.sites.title',
  titleFallback: 'Sites touristiques',
  subtitleKey: 'travel.sites.subtitle',
  searchPlaceholderKey: 'travel.search.site',
  searchKeys: ['name', 'description'],
  defaultSort: 'name',
  statusField: 'status',
  statusMap,
  columns: [
    { key: 'name', label: 'travel.field.name', sortable: true },
    { key: 'city_id', label: 'travel.field.city', sortable: true },
    { key: 'status', label: 'travel.field.status', sortable: true }
  ],
  fields: [
    { key: 'name', label: 'travel.field.name', type: 'text', required: true, max: 160 },
    { key: 'description', label: 'travel.field.description', type: 'textarea' },
    { key: 'city_id', label: 'travel.field.city', type: 'select', source: 'cities' },
    { key: 'latitude', label: 'travel.sites.field.latitude', type: 'number', step: 'any' },
    { key: 'longitude', label: 'travel.sites.field.longitude', type: 'number', step: 'any' },
    {
      key: 'status', label: 'travel.field.status', type: 'select',
      options: [
        { value: 'active', label: t('travel.status.active', 'Actif') },
        { value: 'disabled', label: t('travel.sites.statusDisabled', 'Désactivé') }
      ]
    }
  ],
  defaults: { status: 'active' }
}))

const siteDisplay = computed(() => ({
  city_id: (row, value) => {
    const opt = cityOptions.value.find((c) => String(c.value) === String(value))
    return opt ? opt.label : (value ?? '-')
  }
}))

onMounted(loadCities)
</script>
