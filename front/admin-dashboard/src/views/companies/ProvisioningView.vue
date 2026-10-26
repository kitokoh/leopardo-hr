<template>
  <div class="space-y-8 animate-fade-in">
    <!-- En-tête -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white uppercase">
          {{ t('provisioning.title', 'Provisionnement') }}
        </h1>
        <p class="mt-1 text-lg font-medium text-slate-500 dark:text-slate-400">
          {{ t('provisioning.subtitle', 'Où chaque tenant en est de son onboarding — et lesquels sont bloqués.') }}
        </p>
      </div>
      <div class="flex flex-wrap gap-3">
        <button class="btn-secondary py-2.5 shadow-glass-sm" :disabled="isLoading" @click="load(true)">
          <ArrowPathIcon class="mr-2 h-4 w-4" :class="{ 'animate-spin': isLoading }" />
          {{ t('provisioning.refresh', 'Actualiser') }}
        </button>
      </div>
    </div>

    <!-- Bandeau d'accès -->
    <div
      v-if="!canView"
      class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 dark:border-amber-900/40 dark:bg-amber-900/20"
    >
      <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
      <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
        {{ t('provisioning.noPermission', "Votre rôle ne porte pas « companies.view » : cet écran est en lecture seule impossible.") }}
      </p>
    </div>

    <template v-else>
      <!-- Erreur -->
      <div
        v-if="errorMessage"
        class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 dark:border-red-900/40 dark:bg-red-900/20"
      >
        <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-400" />
        <div class="text-sm font-medium text-red-800 dark:text-red-300">
          <p class="font-bold">{{ t('provisioning.loadError', 'Chargement impossible') }}</p>
          <p class="mt-1">{{ errorMessage }}</p>
        </div>
      </div>

      <!-- Agrégats -->
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4 animate-slide-up">
        <StatsCard
          :title="t('provisioning.statsTenants', 'Tenants')"
          :value="summary.tenants ?? '—'"
          icon="BuildingOffice2Icon"
          color="blue"
        />
        <StatsCard
          :title="t('provisioning.statsWithoutSite', 'Sans établissement')"
          :value="summary.without_site ?? '—'"
          icon="ExclamationTriangleIcon"
          color="red"
        />
        <StatsCard
          :title="t('provisioning.statsIncomplete', 'Onboarding incomplet')"
          :value="summary.onboarding_incomplete ?? '—'"
          icon="ClockIcon"
          color="amber"
        />
        <StatsCard
          :title="t('provisioning.statsStalled', 'Provisionnements bloqués')"
          :value="summary.stalled_provisionings ?? '—'"
          icon="ServerStackIcon"
          color="purple"
        />
      </div>

      <!-- Filtres -->
      <div class="card px-6 py-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">
              {{ t('provisioning.listTitle', 'Progression par tenant') }}
            </h2>
            <p class="text-sm text-slate-500">
              {{ t('provisioning.listSub', 'Faits de provisionnement uniquement — aucune donnée métier client.') }}
            </p>
          </div>
          <div class="flex flex-wrap gap-2 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl w-fit">
            <button
              v-for="f in filters"
              :key="f.id"
              type="button"
              class="rounded-lg px-3 py-1.5 text-[11px] font-black uppercase tracking-wide transition"
              :class="activeFilter === f.id
                ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white'
                : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white'"
              @click="activeFilter = f.id"
            >
              {{ f.label }} <span class="ml-1 opacity-70">{{ f.count }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Tableau -->
      <div class="card overflow-hidden">
        <div v-if="isLoading" class="px-6 py-16 text-center">
          <span class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-brand-500 border-t-transparent"></span>
          <p class="mt-3 text-sm font-medium text-slate-500">
            {{ t('provisioning.loading', 'Lecture du provisionnement…') }}
          </p>
        </div>

        <div v-else-if="filteredTenants.length === 0" class="px-6 py-16 text-center">
          <p class="text-sm font-medium text-slate-500">
            {{ t('provisioning.empty', 'Aucun tenant ne correspond à ce filtre.') }}
          </p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="border-b border-slate-200/60 bg-slate-50/60 dark:border-slate-800/60 dark:bg-slate-900/40">
              <tr class="text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">
                <th class="px-6 py-3">{{ t('provisioning.colTenant', 'Tenant') }}</th>
                <th class="px-6 py-3">{{ t('provisioning.colStep', 'Dernière étape') }}</th>
                <th class="px-6 py-3">{{ t('provisioning.colSites', 'Établissements') }}</th>
                <th class="px-6 py-3">{{ t('provisioning.colModules', 'Modules actifs') }}</th>
                <th class="px-6 py-3">{{ t('provisioning.colAge', 'Créé il y a') }}</th>
                <th class="px-6 py-3 text-right">{{ t('provisioning.colActions', 'Journal') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
              <tr
                v-for="tenant in filteredTenants"
                :key="tenant.id"
                class="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/30"
              >
                <td class="px-6 py-4">
                  <div class="font-bold text-slate-900 dark:text-white">{{ tenant.name }}</div>
                  <div class="text-xs text-slate-500">
                    {{ tenant.slug }}<span v-if="tenant.country"> · {{ tenant.country }}</span>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <span
                    class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[10px] font-black uppercase tracking-wide"
                    :class="stepClass(tenant.provisioning_step)"
                  >
                    <span class="h-1.5 w-1.5 rounded-full" :class="stepDot(tenant.provisioning_step)"></span>
                    {{ stepLabel(tenant.provisioning_step) }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <span
                    class="font-bold"
                    :class="tenant.has_site ? 'text-slate-900 dark:text-white' : 'text-red-600 dark:text-red-400'"
                  >
                    {{ tenant.sites }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                  <span v-if="tenant.modules_active.length === 0" class="text-slate-400">—</span>
                  <span v-else>{{ tenant.modules_active.length }}</span>
                </td>
                <td class="px-6 py-4 text-sm text-slate-500">
                  {{ ageOf(tenant.created_at) }}
                </td>
                <td class="px-6 py-4 text-right">
                  <!-- Le détail tenant EST le journal d'événements : on renvoie
                       vers la fiche existante plutôt que d'inventer un second
                       écran (règle « aucune seconde console »). -->
                  <router-link
                    :to="`/companies/${tenant.id}`"
                    class="text-xs font-bold text-brand-600 hover:underline dark:text-brand-400"
                  >
                    {{ t('provisioning.openJournal', "Voir l'historique") }}
                  </router-link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Échecs de provisioning -->
      <div v-if="failures.length > 0" class="card overflow-hidden border-red-200/60 dark:border-red-900/40">
        <div class="border-b border-red-100 px-6 py-5 dark:border-red-900/30">
          <h2 class="text-xl font-bold text-red-700 dark:text-red-400">
            {{ t('provisioning.failuresTitle', "Échecs et blocages d'onboarding") }}
          </h2>
          <p class="text-sm text-slate-500">
            {{ t('provisioning.failuresSub', 'Provisionnements échoués ou sans progression depuis plus de 15 minutes.') }}
          </p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="border-b border-slate-200/60 bg-slate-50/60 dark:border-slate-800/60 dark:bg-slate-900/40">
              <tr class="text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">
                <th class="px-6 py-3">{{ t('provisioning.colEmail', 'Compte (masqué)') }}</th>
                <th class="px-6 py-3">{{ t('provisioning.colStatus', 'Statut') }}</th>
                <th class="px-6 py-3">{{ t('provisioning.colSince', 'Depuis') }}</th>
                <th class="px-6 py-3">{{ t('provisioning.colError', 'Erreur') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
              <tr v-for="(f, i) in failures" :key="i">
                <td class="px-6 py-4 font-mono text-xs text-slate-600 dark:text-slate-300">{{ f.email ?? '—' }}</td>
                <td class="px-6 py-4">
                  <span class="rounded-lg bg-red-100 px-2.5 py-1 text-[10px] font-black uppercase text-red-700 dark:bg-red-900/30 dark:text-red-400">
                    {{ f.status }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-slate-500">{{ ageOf(f.created_at) }}</td>
                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                  {{ f.error ?? '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
/**
 * #8630 (BOS-S-01) — Écran « Provisionnement » du superadmin.
 *
 * Le contrat BACKEND existe déjà (`GET /platform/provisioning`, #8626/#8627) et
 * renvoie déjà tout ce dont l'opérateur a besoin : progression par tenant,
 * tenants sans établissement, provisionnements bloqués, catalogue des
 * verticales. L'issue #8630 ne demandait donc pas un second service — elle
 * constatait qu'AUCUN écran ne consommait ce contrat, donc qu'un tenant créé
 * mais inutilisable restait invisible. Ce fichier est cet écran.
 *
 * ## Décisions
 *
 * - **Aucune seconde console.** Le « détail tenant » pointe vers la fiche
 *   entreprise existante (`/companies/:id`), qui porte déjà le journal
 *   d'événements : créer un écran de détail dédié aurait dupliqué l'historique.
 * - **Aucun calcul côté client.** Les agrégats (sans établissement, incomplet,
 *   bloqué) viennent du serveur, qui seul connaît les seuils (15 min de
 *   blocage, 7 jours d'incomplet). Recalculer ici ferait diverger l'écran de
 *   la vérité serveur au premier changement de seuil.
 * - **Lecture seule.** L'issue demande « toute action est journalisée » : il
 *   n'y a aucune action sur cet écran hormis « Actualiser », qui est une
 *   lecture. Aucune mutation n'est exposée, donc rien à journaliser — et c'est
 *   le choix le plus sûr pour un écran de diagnostic.
 * - **Permission vérifiée côté client ET côté serveur.** `hasPermission` ne
 *   fait que masquer l'écran ; le middleware `platform.permission:companies.view`
 *   reste la seule autorité (une UI ne protège rien).
 */
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale'
import { useAuthStore } from '@/stores/auth'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import { ArrowPathIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

const toast = useToast()
const authStore = useAuthStore()
const localeStore = useLocaleStore()

/** `t` suit la locale active du store, comme le reste de la console.
 *  `vue-i18n` n'est pas une dependance de cette app : le dictionnaire local
 *  (`@/i18n/index.js`) est la source unique. */
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const canView = computed(() => authStore.hasPermission('companies.view'))

const isLoading = ref(false)
const errorMessage = ref('')

const summary = ref({})
const tenants = ref([])
const failures = ref([])

const activeFilter = ref('all')

/**
 * Les compteurs des filtres sont calculés depuis la liste reçue, pas depuis le
 * résumé serveur : un tenant peut être à la fois « sans établissement » et
 * « incomplet », et additionner les deux compteurs donnerait un total faux.
 */
const filters = computed(() => [
  { id: 'all', label: t('provisioning.filterAll', 'Tous'), count: tenants.value.length },
  {
    id: 'no_site',
    label: t('provisioning.filterNoSite', 'Sans établissement'),
    count: tenants.value.filter((x) => !x.has_site).length,
  },
  {
    id: 'blocked',
    label: t('provisioning.filterBlocked', 'Bloqués'),
    count: tenants.value.filter((x) => x.provisioning_step === 'blocked_no_site').length,
  },
])

const filteredTenants = computed(() => {
  switch (activeFilter.value) {
    case 'no_site':
      return tenants.value.filter((x) => !x.has_site)
    case 'blocked':
      return tenants.value.filter((x) => x.provisioning_step === 'blocked_no_site')
    default:
      return tenants.value
  }
})

const STEP_LABELS = {
  blocked_no_site: 'Sans établissement',
  onboarding_pending: 'Onboarding non démarré',
  module_pending: 'Aucun module actif',
  operational: 'Opérationnel',
}

function stepLabel(step) {
  return STEP_LABELS[step] ?? step ?? '—'
}

function stepClass(step) {
  if (step === 'operational') {
    return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400'
  }
  if (step === 'blocked_no_site') {
    return 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'
  }
  return 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400'
}

function stepDot(step) {
  if (step === 'operational') return 'bg-emerald-500'
  if (step === 'blocked_no_site') return 'bg-red-500'
  return 'bg-amber-500'
}

/** Ancienneté lisible. `null` reste `null` : on n'invente pas une date. */
function ageOf(iso) {
  if (!iso) return '—'
  const then = new Date(iso).getTime()
  if (Number.isNaN(then)) return '—'
  const days = Math.floor((Date.now() - then) / 86_400_000)
  if (days >= 1) return `${days} j`
  const hours = Math.floor((Date.now() - then) / 3_600_000)
  if (hours >= 1) return `${hours} h`
  return "< 1 h"
}

async function load(refresh = false) {
  if (!canView.value) return
  isLoading.value = true
  errorMessage.value = ''
  try {
    const { data } = await api.get('/platform/provisioning', {
      params: { limit: 200, ...(refresh ? { refresh: 1 } : {}) },
    })
    summary.value = data?.summary ?? {}
    tenants.value = Array.isArray(data?.tenants) ? data.tenants : []
    failures.value = Array.isArray(data?.failures) ? data.failures : []
  } catch (error) {
    // On distingue le refus d'autorisation du reste : un 403 n'est pas une
    // panne, et le message doit le dire pour éviter un ticket inutile.
    const status = error?.response?.status
    errorMessage.value =
      status === 403
        ? t('provisioning.forbidden', "Accès refusé — permission « companies.view » requise.")
        : error?.response?.data?.message || error?.message || 'Erreur inconnue'
    if (status !== 403) toast.error(t('provisioning.loadError', 'Chargement impossible'))
  } finally {
    isLoading.value = false
  }
}

onMounted(() => load())
</script>
