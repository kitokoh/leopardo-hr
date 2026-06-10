<template>
  <div class="space-y-8 animate-fade-in">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white uppercase">Portefeuille Clients</h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          Adoption, risque, revenus récurrents et prochaine action par entreprise.
        </p>
      </div>
      <div class="flex flex-wrap gap-3">
        <button class="btn-primary py-2.5 shadow-premium" @click="openCreateClient">
          <PlusIcon class="mr-2 h-5 w-5" />
          Nouveau Client
        </button>
        <button class="btn-secondary py-2.5 shadow-glass-sm" :disabled="isLoading" @click="fetchPortfolio">
          <ArrowPathIcon class="mr-2 h-4 w-4" :class="{ 'animate-spin': isLoading }" />
          Actualiser
        </button>
      </div>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4 animate-slide-up">
      <StatsCard title="Clients Suivis" :value="summary.companies" icon="BuildingOffice2Icon" color="blue" />
      <StatsCard title="Clients Actifs" :value="summary.active_companies" icon="UsersIcon" color="green" />
      <StatsCard title="MRR Global" :value="formattedMrr" icon="BanknotesIcon" color="purple" />
      <StatsCard title="Alerte Risque" :value="summary.risk.high" icon="ExclamationTriangleIcon" color="red" />
    </div>

    <div class="card animate-slide-up" style="animation-delay: 0.1s">
      <div class="flex flex-col gap-4 border-b border-slate-200/50 dark:border-slate-800/50 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">Répertoire des Entreprises</h2>
          <p class="text-sm text-slate-500">Liste classée par score de santé et priorité commerciale.</p>
        </div>
        <div class="flex gap-2 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl w-fit">
          <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-900/30">
            <div class="h-1.5 w-1.5 rounded-full bg-red-500"></div>
            <span class="text-[10px] font-black uppercase text-red-700 dark:text-red-400">High {{ summary.risk.high }}</span>
          </div>
          <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-900/30">
            <div class="h-1.5 w-1.5 rounded-full bg-amber-500"></div>
            <span class="text-[10px] font-black uppercase text-amber-700 dark:text-amber-400">Med {{ summary.risk.medium }}</span>
          </div>
          <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-900/30">
            <div class="h-1.5 w-1.5 rounded-full bg-emerald-500"></div>
            <span class="text-[10px] font-black uppercase text-emerald-700 dark:text-emerald-400">Low {{ summary.risk.low }}</span>
          </div>
        </div>
      </div>

      <div v-if="isLoading && items.length === 0" class="flex flex-col items-center justify-center p-20 gap-4">
        <div class="h-10 w-10 animate-spin rounded-full border-4 border-brand-500 border-t-transparent"></div>
        <p class="text-sm font-bold text-slate-500">Synchronisation du portefeuille...</p>
      </div>

      <div v-else-if="errorMessage" class="m-6 rounded-2xl bg-red-50 p-8 text-center border border-red-100 dark:bg-red-950/20 dark:border-red-900/30">
        <p class="text-sm font-bold text-red-600">{{ errorMessage }}</p>
        <button class="btn-secondary mt-4" @click="fetchPortfolio">Réessayer</button>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200/50 dark:divide-slate-800/50">
          <thead class="bg-slate-50/50 dark:bg-slate-900/30">
            <tr>
              <th class="px-6 py-4 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Entreprise</th>
              <th class="px-6 py-4 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Plan & MRR</th>
              <th class="px-6 py-4 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Santé Opér.</th>
              <th class="px-6 py-4 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Pointage (30j)</th>
              <th class="px-6 py-4 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Action Recommandée</th>
              <th class="px-6 py-4 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Gestion</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200/50 dark:divide-slate-800/50">
            <tr v-for="item in sortedItems" :key="item.company.id" class="group hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
              <td class="whitespace-nowrap px-6 py-5">
                <div class="flex items-center gap-3">
                  <div class="h-10 w-10 rounded-xl bg-brand-500/10 flex items-center justify-center text-brand-600 dark:text-brand-400 font-black text-xs uppercase">
                    {{ item.company.name.substring(0, 2) }}
                  </div>
                  <div>
                    <div class="font-bold text-slate-900 dark:text-white uppercase tracking-tight">{{ item.company.name }}</div>
                    <div class="text-[10px] font-black text-slate-400 mt-0.5 uppercase tracking-widest">
                      {{ item.company.status }} · {{ item.company.country }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="whitespace-nowrap px-6 py-5">
                <div class="font-bold text-slate-700 dark:text-slate-300 text-sm">{{ item.plan.name || 'SANS PLAN' }}</div>
                <div class="text-xs font-black text-brand-600 dark:text-brand-400 mt-0.5">{{ formatCurrency(item.subscription.mrr, item.subscription.currency) }}/m</div>
              </td>
              <td class="whitespace-nowrap px-6 py-5">
                <div class="flex items-center gap-3">
                  <span :class="riskClass(item.risk_level)">{{ item.risk_level }}</span>
                  <span class="text-sm font-black text-slate-900 dark:text-white">{{ item.health_score }}%</span>
                </div>
              </td>
              <td class="whitespace-nowrap px-6 py-5">
                <div class="font-bold text-slate-700 dark:text-slate-300 text-sm">{{ item.attendance_logs_30d }} logs</div>
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-0.5">{{ item.employees_active }} actifs</div>
              </td>
              <td class="px-6 py-5">
                <div v-if="item.next_action" class="flex items-center gap-2">
                  <div class="h-1.5 w-1.5 rounded-full bg-brand-500"></div>
                  <span class="text-sm font-bold text-slate-600 dark:text-slate-400">{{ item.next_action.label }}</span>
                </div>
                <span v-else class="text-[10px] font-black text-slate-300 uppercase tracking-widest">RAS</span>
              </td>
              <td class="whitespace-nowrap px-6 py-5 text-right">
                <router-link class="inline-flex items-center px-4 py-2 rounded-xl bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 text-xs font-black uppercase tracking-widest hover:bg-brand-100 dark:hover:bg-brand-900/50 transition-all shadow-glass-sm group-hover:shadow-glass hover:-translate-y-0.5" :to="`/companies/${item.company.id}`">
                  Ouvrir
                </router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Modal -->
    <Teleport to="body">
      <div
        v-if="showCreateModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 px-4 py-8 backdrop-blur-md"
        role="dialog"
        aria-modal="true"
        aria-labelledby="create-client-title"
      >
        <div class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-3xl border border-white/10 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-950 sm:p-0">
          <div class="sticky top-0 z-20 flex items-center justify-between border-b border-slate-100 bg-white/90 px-6 py-5 dark:border-slate-800 dark:bg-slate-950/90 backdrop-blur-md">
            <div>
              <p class="text-[10px] font-black uppercase tracking-[0.3em] text-brand-600 dark:text-brand-400">Système</p>
              <h2 id="create-client-title" class="text-xl font-black tracking-tight text-slate-900 dark:text-white uppercase">
                Provisionnement Client
              </h2>
            </div>
            <button
              class="rounded-xl border border-slate-200 p-2 text-slate-400 transition hover:bg-slate-100 dark:border-slate-800 dark:hover:bg-slate-900"
              type="button"
              @click="closeCreateClient"
            >
              <XMarkIcon class="h-6 w-6" />
            </button>
          </div>

          <form class="p-8 space-y-8" @submit.prevent="submitCreateClient">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
              <div class="space-y-1.5">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500 ml-1">Nom entreprise *</label>
                <input v-model.trim="createForm.name" class="form-input" required maxlength="100" placeholder="Ex: TECHCORP ALGERIE" />
              </div>
              <div class="space-y-1.5">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500 ml-1">Email contact *</label>
                <input v-model.trim="createForm.email" class="form-input" required type="email" maxlength="150" placeholder="contact@techcorp.example" />
              </div>
              <div class="space-y-1.5">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500 ml-1">Pays *</label>
                <select v-model="createForm.country" class="form-input" required>
                  <option v-for="country in countryDefaults" :key="country.country" :value="country.country">
                    {{ country.label }} ({{ country.country }})
                  </option>
                </select>
              </div>
              <div class="space-y-1.5">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500 ml-1">Ville de déploiement *</label>
                <input v-model.trim="createForm.city" class="form-input" required maxlength="100" placeholder="Alger" />
              </div>
            </div>

            <!-- Regional Defaults Summary -->
            <div class="rounded-2xl border border-brand-100 bg-brand-50/50 p-5 dark:border-brand-900/30 dark:bg-brand-950/20">
              <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                  <p class="text-[9px] font-black uppercase tracking-widest text-brand-600/70">Devise</p>
                  <p class="mt-1 text-sm font-black text-slate-900 dark:text-white uppercase">{{ selectedCountryDefault.currency }}</p>
                </div>
                <div>
                  <p class="text-[9px] font-black uppercase tracking-widest text-brand-600/70">Fuseau Horaire</p>
                  <p class="mt-1 text-sm font-black text-slate-900 dark:text-white">{{ selectedCountryDefault.timezone }}</p>
                </div>
                <div>
                  <p class="text-[9px] font-black uppercase tracking-widest text-brand-600/70">Langue Défaut</p>
                  <p class="mt-1 text-sm font-black text-slate-900 dark:text-white uppercase">{{ selectedCountryDefault.language }}</p>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 pt-4">
              <div class="space-y-1.5">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500 ml-1">Prénom Manager *</label>
                <input v-model.trim="createForm.manager_first_name" class="form-input" required maxlength="100" placeholder="Amina" />
              </div>
              <div class="space-y-1.5">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500 ml-1">Nom Manager *</label>
                <input v-model.trim="createForm.manager_last_name" class="form-input" required maxlength="100" placeholder="Benali" />
              </div>
              <div class="space-y-1.5 sm:col-span-2">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500 ml-1">Email Manager Principal *</label>
                <input v-model.trim="createForm.manager_email" class="form-input" required type="email" maxlength="150" placeholder="manager@techcorp.example" />
              </div>
            </div>

            <div class="flex items-center gap-4 p-5 rounded-2xl border border-slate-100 bg-slate-50 dark:bg-slate-900 dark:border-slate-800">
              <Switch
                v-model="activateImmediately"
                :class="[activateImmediately ? 'bg-brand-600' : 'bg-slate-200 dark:bg-slate-700', 'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950']"
              >
                <span class="sr-only">Activer immédiatement</span>
                <span
                  aria-hidden="true"
                  :class="[activateImmediately ? 'translate-x-5' : 'translate-x-0', 'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out']"
                />
              </Switch>
              <div>
                <span class="block text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight">Activer le client immédiatement</span>
                <span class="mt-0.5 block text-xs font-medium text-slate-500">Sinon le client reste en essai (trial).</span>
              </div>
            </div>

            <div v-if="createError" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700 dark:border-red-900/40 dark:bg-red-950/30">
              <ExclamationCircleIcon class="inline-block h-5 w-5 mr-2 -mt-0.5" />
              {{ createError }}
            </div>

            <div class="flex flex-col-reverse gap-3 pt-4 sm:flex-row sm:justify-end sm:gap-4">
              <button class="btn-secondary px-8 py-3 uppercase tracking-widest text-xs font-black" type="button" @click="closeCreateClient">
                Annuler
              </button>
              <button class="btn-primary px-10 py-3 uppercase tracking-widest text-xs font-black shadow-premium" type="submit" :disabled="isCreating">
                <PlusIcon v-if="!isCreating" class="mr-2 h-4 w-4" />
                <span v-else class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                {{ isCreating ? 'Provisionnement...' : 'Créer le client' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { Switch } from '@headlessui/vue'
import {
  BuildingOffice2Icon,
  UsersIcon,
  BanknotesIcon,
  ExclamationTriangleIcon,
  ArrowPathIcon,
  PlusIcon,
  XMarkIcon,
  ExclamationCircleIcon
} from '@heroicons/vue/24/outline'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'

const router = useRouter()
const toast = useToast()
const isLoading = ref(false)
const errorMessage = ref('')
const showCreateModal = ref(false)
const isCreating = ref(false)
const createError = ref('')
const activateImmediately = ref(false)
const countryDefaults = ref([
  { country: 'DZ', label: 'Algerie', language: 'fr', currency: 'DZD', timezone: 'Africa/Algiers' },
])
const createForm = ref(defaultCreateForm())
const summary = ref({
  companies: 0,
  active_companies: 0,
  mrr: 0,
  risk: { high: 0, medium: 0, low: 0 },
})
const items = ref([])

const sortedItems = computed(() => {
  const rank = { high: 0, medium: 1, low: 2 }
  return [...items.value].sort((a, b) => {
    const riskDiff = (rank[a.risk_level] ?? 3) - (rank[b.risk_level] ?? 3)
    return riskDiff !== 0 ? riskDiff : a.health_score - b.health_score
  })
})

const formattedMrr = computed(() => formatCurrency(summary.value.mrr, 'EUR'))
const selectedCountryDefault = computed(() => {
  return countryDefaults.value.find((country) => country.country === createForm.value.country)
    || countryDefaults.value[0]
})

async function fetchPortfolio() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await api.get('/platform/companies/health')
    summary.value = response.data?.data?.summary || summary.value
    items.value = response.data?.data?.items || []
  } catch (error) {
    console.error('Failed to load company portfolio:', error)
    errorMessage.value = 'Impossible de charger le cockpit clients.'
  } finally {
    isLoading.value = false
  }
}

async function fetchCountryDefaults() {
  try {
    const response = await api.get('/platform/country-defaults')
    const countries = response.data?.data || []
    if (Array.isArray(countries) && countries.length > 0) {
      countryDefaults.value = countries
      if (!countries.some((country) => country.country === createForm.value.country)) {
        createForm.value.country = countries[0].country
      }
    }
  } catch (error) {
    console.error('Failed to load country defaults:', error)
  }
}

function defaultCreateForm() {
  return {
    name: '',
    email: '',
    country: 'DZ',
    city: '',
    manager_first_name: '',
    manager_last_name: '',
    manager_email: '',
  }
}

function openCreateClient() {
  createForm.value = {
    ...defaultCreateForm(),
    country: countryDefaults.value[0]?.country || 'DZ',
  }
  activateImmediately.value = false
  createError.value = ''
  showCreateModal.value = true
}

function closeCreateClient() {
  if (isCreating.value) return
  showCreateModal.value = false
}

async function submitCreateClient() {
  if (isCreating.value) return
  isCreating.value = true
  createError.value = ''

  try {
    const payload = {
      ...createForm.value,
      country: createForm.value.country.toUpperCase(),
      status: activateImmediately.value ? 'active' : 'trial',
    }
    const response = await api.post('/platform/companies', payload)
    const company = response.data?.data?.company

    showCreateModal.value = false
    toast.success('Client créé et invitation manager envoyée.')
    await fetchPortfolio()

    if (company?.id) {
      router.push(`/companies/${company.id}`)
    }
  } catch (error) {
    console.error('Failed to create platform company:', error)
    createError.value = error.response?.data?.message || 'Impossible de creer ce client.'
  } finally {
    isCreating.value = false
  }
}

function formatCurrency(value, currency = 'EUR') {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: currency || 'EUR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0))
}

function riskClass(risk) {
  const classes = {
    high: 'rounded-lg bg-red-100 dark:bg-red-900/30 px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800',
    medium: 'rounded-lg bg-amber-100 dark:bg-amber-900/30 px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800',
    low: 'rounded-lg bg-emerald-100 dark:bg-emerald-900/30 px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800',
  }
  return classes[risk] || classes.medium
}

onMounted(() => {
  fetchPortfolio()
  fetchCountryDefaults()
})
</script>

<style scoped>
.form-input {
  @apply block w-full rounded-2xl border border-slate-200 bg-white/50 px-4 py-3 text-sm font-bold text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-slate-800 dark:bg-slate-950/50 dark:text-white backdrop-blur-sm placeholder:text-slate-400;
}
</style>
