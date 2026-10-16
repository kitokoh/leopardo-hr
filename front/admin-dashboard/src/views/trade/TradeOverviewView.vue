<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('trade.overview.title', 'International Trade') }}
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('trade.overview.subtitle', 'Suivi de la verticale et état réel des intégrations externes.') }}
      </p>
    </div>

    <!-- Périmètre : compteurs uniquement, jamais le contenu commercial privé. -->
    <div
      class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300"
      role="note"
    >
      {{ t('trade.overview.scopeNotice', 'Compteurs uniquement : les prix, offres, messages et documents des clients ne sont pas exposés à l’administration plateforme.') }}
    </div>

    <div v-if="loading" class="flex items-center justify-center py-16" role="status">
      <div class="h-10 w-10 animate-spin rounded-full border-b-2 border-brand-600"></div>
    </div>

    <div
      v-else-if="loadError"
      class="rounded-2xl border border-rose-200 bg-rose-50 px-6 py-5 text-sm text-rose-700 dark:border-rose-800 dark:bg-rose-900/20 dark:text-rose-300"
      role="alert"
    >
      <p class="font-medium">{{ t('trade.overview.loadError', 'Impossible de charger le suivi de la verticale.') }}</p>
      <p class="mt-1">{{ loadError }}</p>
      <button class="btn-primary mt-3" @click="init">
        {{ t('trade.common.retry', 'Réessayer') }}
      </button>
    </div>

    <template v-else>
      <!-- ── Compteurs ─────────────────────────────────────────────────── -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="card in companyCards"
          :key="card.key"
          class="glass-card rounded-2xl px-5 py-4 ring-1 ring-slate-200 dark:ring-slate-700"
        >
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
            {{ card.label }}
          </p>
          <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ card.value }}</p>
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <div
          v-for="bucket in countBuckets"
          :key="bucket.key"
          class="glass-card rounded-2xl px-5 py-4 ring-1 ring-slate-200 dark:ring-slate-700"
        >
          <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ bucket.label }}</h2>
          <p v-if="!bucket.rows.length" class="mt-2 text-sm text-slate-500 dark:text-slate-400">
            {{ t('trade.common.noData', 'Aucune donnée') }}
          </p>
          <ul v-else class="mt-3 space-y-1.5">
            <li
              v-for="row in bucket.rows"
              :key="row.status"
              class="flex items-center justify-between text-sm"
            >
              <span class="text-slate-600 dark:text-slate-300">{{ row.status }}</span>
              <span class="font-semibold text-slate-900 dark:text-white">{{ row.count }}</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- ── Intégrations : état RÉEL, jamais présenté comme actif si absent ── -->
      <div class="glass-card rounded-2xl px-5 py-4 ring-1 ring-slate-200 dark:ring-slate-700">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-white">
            {{ t('trade.integrations.title', 'Intégrations externes') }}
          </h2>
          <span
            class="rounded-full px-3 py-1 text-xs font-medium"
            :class="integrationsSummary.unconfigured > 0
              ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
              : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'"
          >
            {{ integrationsSummary.configured }} / {{ integrationsSummary.total }}
            {{ t('trade.integrations.configured', 'configurées') }}
          </span>
        </div>

        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
          {{ t('trade.integrations.notice', 'Une intégration non configurée est présentée comme indisponible : aucune donnée n’est simulée pour la masquer.') }}
        </p>

        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <th class="px-3 py-2">{{ t('trade.integrations.colKey', 'Intégration') }}</th>
                <th class="px-3 py-2">{{ t('trade.integrations.colMode', 'Mode') }}</th>
                <th class="px-3 py-2">{{ t('trade.integrations.colState', 'État') }}</th>
                <th class="px-3 py-2">{{ t('trade.integrations.colReason', 'Détail') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="integration in integrations"
                :key="integration.key"
                class="border-t border-slate-100 dark:border-slate-800"
              >
                <td class="px-3 py-3">
                  <p class="font-medium text-slate-900 dark:text-white">{{ integration.label }}</p>
                  <p class="text-xs text-slate-500 dark:text-slate-400">{{ integration.purpose }}</p>
                </td>
                <td class="px-3 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">
                  {{ integration.mode }}
                </td>
                <td class="px-3 py-3">
                  <span
                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="isIntegrationOperational(integration)
                      ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                      : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300'"
                  >
                    {{ isIntegrationOperational(integration)
                      ? t('trade.integrations.stateOperational', 'Opérationnelle')
                      : t('trade.integrations.stateUnconfigured', 'Non configurée') }}
                  </span>
                </td>
                <td class="px-3 py-3 text-xs text-slate-500 dark:text-slate-400">
                  {{ integration.reason || '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Rôles métier de la verticale ──────────────────────────────── -->
      <div class="glass-card rounded-2xl px-5 py-4 ring-1 ring-slate-200 dark:ring-slate-700">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">
          {{ t('trade.roles.title', 'Rôles métier Trade') }}
        </h2>
        <div class="mt-3 flex flex-wrap gap-2">
          <span
            v-for="role in roles"
            :key="role"
            class="rounded-full bg-slate-100 px-3 py-1 font-mono text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300"
          >
            trade.role:{{ role }}
          </span>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { getTradeIntegrations, getTradeOverview, isIntegrationOperational, tradePayload } from '@/services/trade'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const loading = ref(true)
const loadError = ref('')

const companies = ref({ scanned: 0, with_trade: 0, with_activity: 0 })
const counts = ref({ deals: [], rfqs: [], quotes: [], orders: [] })
const roles = ref([])
const integrations = ref([])
const integrationsSummary = ref({ total: 0, configured: 0, unconfigured: 0 })

const companyCards = computed(() => [
  { key: 'scanned', label: t('trade.overview.companiesScanned', 'Entreprises analysées'), value: companies.value.scanned },
  { key: 'with_trade', label: t('trade.overview.companiesWithTrade', 'Avec la verticale Trade'), value: companies.value.with_trade },
  { key: 'with_activity', label: t('trade.overview.companiesWithActivity', 'Avec activité commerciale'), value: companies.value.with_activity },
  { key: 'unconfigured', label: t('trade.overview.integrationsUnconfigured', 'Intégrations non configurées'), value: integrationsSummary.value.unconfigured }
])

const countBuckets = computed(() => [
  { key: 'deals', label: t('trade.counts.deals', 'Dossiers (deals)'), rows: counts.value.deals || [] },
  { key: 'rfqs', label: t('trade.counts.rfqs', 'Demandes de prix (RFQ)'), rows: counts.value.rfqs || [] },
  { key: 'quotes', label: t('trade.counts.quotes', 'Offres commerciales'), rows: counts.value.quotes || [] },
  { key: 'orders', label: t('trade.counts.orders', 'Commandes'), rows: counts.value.orders || [] }
])

async function init() {
  loading.value = true
  loadError.value = ''

  try {
    const [overviewResponse, integrationsResponse] = await Promise.all([
      getTradeOverview(),
      getTradeIntegrations()
    ])

    const overview = tradePayload(overviewResponse)
    companies.value = overview.companies || companies.value
    counts.value = overview.counts || counts.value
    roles.value = overview.roles || []

    const integrationData = tradePayload(integrationsResponse)
    integrations.value = integrationData.integrations || []
    integrationsSummary.value = integrationData.summary || integrationsSummary.value
  } catch (error) {
    // Erreur API surfacée telle quelle : jamais d'écran vide silencieux.
    loadError.value = error?.response?.data?.message || error?.message || String(error)
  } finally {
    loading.value = false
  }
}

onMounted(init)
</script>
