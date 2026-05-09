<template>
  <div class="space-y-8 animate-fade-in">
    <!-- Header Section -->
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div>
        <div class="flex items-center gap-2 text-brand-600 mb-1">
          <RocketLaunchIcon class="h-5 w-5" />
          <span class="text-xs font-bold uppercase tracking-widest">Aperçu Global</span>
        </div>
        <h1 class="text-3xl font-extrabold text-zinc-900 tracking-tight">Cockpit Stratégique</h1>
        <p class="mt-2 text-zinc-500 max-w-2xl">
          Pilotage de la performance, monitoring de la rétention et gestion des opportunités entrantes en temps réel.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <button
          class="btn-secondary group"
          :disabled="isLoading"
          @click="loadDashboard"
        >
          <ArrowPathIcon :class="['h-4 w-4 mr-2 transition-transform duration-500', isLoading ? 'animate-spin' : 'group-hover:rotate-180']" />
          Actualiser les données
        </button>
        <button class="btn-primary">
          <PlusIcon class="h-4 w-4 mr-2" />
          Nouveau Rapport
        </button>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <StatsCard title="Portefeuille MRR" :value="summary.mrr" icon="CurrencyEuroIcon" color="brand" :change="2.4" changeLabel="vs mois dernier" />
      <StatsCard title="Clients Actifs" :value="summary.active_companies" icon="BuildingOfficeIcon" color="emerald" :change="1.2" />
      <StatsCard title="Indice de Risque" :value="summary.risk.high" icon="ChartBarIcon" color="rose" :change="-5" />
      <StatsCard title="Demandes Support" :value="pendingRequests" icon="ChatBubbleLeftRightIcon" color="amber" />
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 gap-8 xl:grid-cols-3">
      <!-- Priority Companies (Left/Center) -->
      <section class="card xl:col-span-2">
        <div class="card-header bg-zinc-50/50">
          <div>
            <h2 class="text-lg font-bold text-zinc-900">Priorités Clients & Rétention</h2>
            <p class="text-xs font-medium text-zinc-500 mt-0.5">Focus sur les comptes nécessitant une attention immédiate.</p>
          </div>
          <router-link class="btn-ghost text-brand-600 px-3 py-1.5" to="/companies">
            Tout voir
          </router-link>
        </div>

        <div class="divide-y divide-zinc-100">
          <article
            v-for="item in priorityCompanies"
            :key="item.company.id"
            class="group p-6 hover:bg-zinc-50/50 transition-colors"
          >
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center">
              <!-- Company Info -->
              <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-3 mb-2">
                  <div class="h-10 w-10 rounded-xl bg-zinc-100 flex items-center justify-center font-bold text-zinc-500 border border-zinc-200">
                    {{ item.company.name[0] }}
                  </div>
                  <div>
                    <h3 class="font-bold text-zinc-900 truncate">{{ item.company.name }}</h3>
                    <div class="flex items-center gap-2 mt-0.5">
                      <span :class="riskClass(item.risk_level)">{{ riskLabel(item.risk_level) }}</span>
                      <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-tighter">{{ item.plan.name || 'FREE' }}</span>
                    </div>
                  </div>
                </div>

                <div class="grid grid-cols-3 gap-4 mt-4">
                  <div class="rounded-xl bg-zinc-50 p-2 border border-zinc-100">
                    <p class="text-[10px] font-bold text-zinc-400 uppercase">Santé</p>
                    <p class="text-sm font-bold text-zinc-900">{{ item.health_score }}/100</p>
                  </div>
                  <div class="rounded-xl bg-zinc-50 p-2 border border-zinc-100">
                    <p class="text-[10px] font-bold text-zinc-400 uppercase">Usage 30j</p>
                    <p class="text-sm font-bold text-zinc-900">{{ item.attendance_logs_30d }} pts</p>
                  </div>
                  <div class="rounded-xl bg-zinc-50 p-2 border border-zinc-100">
                    <p class="text-[10px] font-bold text-zinc-400 uppercase">Staff</p>
                    <p class="text-sm font-bold text-zinc-900">{{ item.employees_active }}</p>
                  </div>
                </div>
              </div>

              <!-- Recommendation -->
              <div class="lg:w-72 bg-brand-50/30 rounded-2xl p-4 border border-brand-100/50">
                <div class="flex items-center gap-2 text-brand-700 mb-1">
                  <SparklesIcon class="h-4 w-4" />
                  <span class="text-[10px] font-bold uppercase tracking-wider">IA Recommendation</span>
                </div>
                <p class="text-xs font-medium text-zinc-700 leading-relaxed">
                  {{ item.next_action?.label || 'Continuer le monitoring standard. Pas d\'anomalie détectée.' }}
                </p>
              </div>

              <!-- Action -->
              <router-link class="btn-primary whitespace-nowrap lg:self-center shadow-md group-hover:scale-105 transition-transform" :to="`/companies/${item.company.id}`">
                Piloter
              </router-link>
            </div>
          </article>

          <div v-if="priorityCompanies.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
            <div class="h-16 w-16 rounded-full bg-zinc-50 flex items-center justify-center mb-4">
              <CheckBadgeIcon class="h-8 w-8 text-emerald-500" />
            </div>
            <p class="text-zinc-500 font-medium">Tout est sous contrôle.<br/>Aucun client ne nécessite d'action urgente.</p>
          </div>
        </div>
      </section>

      <!-- Sidebar widgets -->
      <div class="space-y-8">
        <!-- Requests -->
        <section class="card overflow-visible">
          <div class="card-header border-b-0 pb-0">
            <h2 class="text-lg font-bold text-zinc-900">Demandes Entrantes</h2>
            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">NEW</span>
          </div>

          <div class="px-6 py-4">
            <div class="space-y-4">
              <article
                v-for="request in pendingCompanyRequests.slice(0, 4)"
                :key="request.id"
                class="relative flex items-center gap-3 p-3 rounded-2xl hover:bg-zinc-50 transition-colors border border-transparent hover:border-zinc-100"
              >
                <div class="h-10 w-10 flex-shrink-0 rounded-xl bg-brand-100 flex items-center justify-center font-bold text-brand-600">
                  {{ request.company_name[0] }}
                </div>
                <div class="flex-1 min-w-0">
                  <h3 class="text-sm font-bold text-zinc-900 truncate">{{ request.company_name }}</h3>
                  <p class="text-xs text-zinc-500 truncate">{{ request.sector || 'Secteur indéfini' }}</p>
                </div>
                <button class="h-8 w-8 flex items-center justify-center rounded-lg hover:bg-white hover:shadow-sm text-zinc-400 hover:text-brand-600 transition-all">
                  <ChevronRightIcon class="h-4 w-4" />
                </button>
              </article>

              <div v-if="pendingCompanyRequests.length === 0" class="py-10 text-center text-zinc-400 text-sm font-medium">
                Aucune demande en attente
              </div>
            </div>
          </div>

          <div class="card-footer px-6 py-4 bg-zinc-50/50 border-t border-zinc-100 rounded-b-2xl">
            <router-link class="flex items-center justify-center w-full text-sm font-bold text-brand-600 hover:text-brand-700 gap-2" to="/support">
              Gérer les demandes
              <ArrowRightIcon class="h-3.5 w-3.5" />
            </router-link>
          </div>
        </section>

        <!-- Intelligence Widget -->
        <section class="brand-gradient rounded-2xl p-6 text-white shadow-xl shadow-brand-200">
          <SparklesIcon class="h-8 w-8 opacity-50 mb-4" />
          <h2 class="text-xl font-bold mb-2 leading-tight">Leopardo AI Insights</h2>
          <p class="text-brand-50 text-sm leading-relaxed mb-6 opacity-90">
            L'analyse des tendances suggère une croissance de 12% du MRR d'ici la fin du trimestre basée sur le pipeline actuel.
          </p>
          <button class="w-full bg-white/20 backdrop-blur-md hover:bg-white/30 text-white font-bold py-2.5 rounded-xl transition-all border border-white/20">
            Voir les prédictions
          </button>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  RocketLaunchIcon,
  ArrowPathIcon,
  PlusIcon,
  SparklesIcon,
  ChevronRightIcon,
  ArrowRightIcon,
  CheckBadgeIcon
} from '@heroicons/vue/24/outline'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'

const isLoading = ref(false)
const summary = ref({
  active_companies: 0,
  companies: 0,
  mrr: 0,
  risk: { high: 0, medium: 0, low: 0 },
})
const portfolioItems = ref([])
const pendingCompanyRequests = ref([])
const pendingRequests = ref(0)

const priorityCompanies = computed(() => {
  const rank = { high: 0, medium: 1, low: 2 }
  return [...portfolioItems.value]
    .sort((a, b) => {
      const riskDiff = (rank[a.risk_level] ?? 3) - (rank[b.risk_level] ?? 3)
      return riskDiff !== 0 ? riskDiff : a.health_score - b.health_score
    })
    .slice(0, 4)
})

async function loadDashboard() {
  isLoading.value = true
  try {
    const [portfolioResponse, requestsResponse] = await Promise.all([
      api.get('/platform/companies/health'),
      api.get('/platform/company-requests', { params: { status: 'pending' } }),
    ])

    summary.value = portfolioResponse.data?.data?.summary || summary.value
    portfolioItems.value = portfolioResponse.data?.data?.items || []
    pendingCompanyRequests.value = requestsResponse.data?.data || []
    pendingRequests.value = requestsResponse.data?.meta?.total || pendingCompanyRequests.value.length
  } catch (error) {
    console.error('Failed to load platform dashboard:', error)
  } finally {
    isLoading.value = false
  }
}

function riskClass(risk) {
  const classes = {
    high: 'inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700 uppercase tracking-wider',
    medium: 'inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 uppercase tracking-wider',
    low: 'inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700 uppercase tracking-wider',
  }
  return classes[risk] || classes.medium
}

function riskLabel(risk) {
  const labels = { high: 'Critique', medium: 'Vigilance', low: 'Sain' }
  return labels[risk] || risk
}

onMounted(loadDashboard)
</script>
