<template>
  <div class="space-y-6">
    <!-- #7725 — fusion des deux entrées Support : une seule entrée de menu,
         deux onglets internes (demandes des entreprises / centre de tickets).
         L'ancienne route `/support-tickets` redirige vers `?tab=tickets`. -->
    <div
      class="flex w-fit flex-wrap gap-2 rounded-2xl bg-slate-200/50 p-1 dark:bg-slate-800/50"
      role="tablist"
      :aria-label="t('navigation.support', 'Support')"
    >
      <button
        v-for="tab in tabs"
        :key="tab.id"
        role="tab"
        :aria-selected="activeTab === tab.id"
        :data-testid="`support-tab-${tab.id}`"
        :class="[
          'rounded-xl px-4 py-2 text-xs font-black uppercase tracking-widest transition-all',
          activeTab === tab.id
            ? 'glass-card text-brand-600 shadow-glass-sm dark:bg-slate-700 dark:text-white'
            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
        ]"
        @click="selectTab(tab.id)"
      >
        {{ tab.label }}
      </button>
    </div>

    <SupportView v-if="activeTab === 'requests'" />
    <SupportTicketsView v-else />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import SupportView from './SupportView.vue'
import SupportTicketsView from './SupportTicketsView.vue'

const route = useRoute()
const router = useRouter()
const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const tabs = computed(() => [
  { id: 'requests', label: t('navigation.support', 'Support') },
  { id: 'tickets', label: t('navigation.supportTickets', 'Centre support client') },
])

// Un lien profond avec `company_id` (fiche entreprise → « Voir tous les
// tickets ») cible le centre de tickets : l'onglet suit la query.
const activeTab = computed(() =>
  route.query.tab === 'tickets' || route.query.company_id ? 'tickets' : 'requests',
)

function selectTab(tab) {
  if (tab === activeTab.value) return
  const query = { ...route.query }
  delete query.tab
  router.replace({ path: '/support', query: tab === 'tickets' ? { ...query, tab: 'tickets' } : query })
}
</script>
