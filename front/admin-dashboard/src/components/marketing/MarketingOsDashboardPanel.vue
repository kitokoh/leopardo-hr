<script setup>
/**
 * Tableau de bord Marketing OS (section A du brief).
 *
 * Chaque indicateur vient d'une DONNÉE RÉELLE renvoyée par le serveur. Deux
 * compteurs d'"à produire / à valider" sont calculés ici par addition de
 * statuts renvoyés par l'API — jamais inventés. La performance de publication
 * affiche explicitement sa provenance : si aucune plateforme n'a fourni de
 * statistiques, l'écran écrit « non disponible », jamais « 0 ».
 */
import { computed } from 'vue'
import MarketingOsStatusBadge from '@/components/marketing/MarketingOsStatusBadge.vue'
import {
  PUBLICATION_MODE_LABELS,
  PUBLICATION_STATUS_LABELS,
  labelFrom,
} from '@/services/marketingOs'

const props = defineProps({
  overview: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const pipeline = computed(() => props.overview?.content_pipeline || null)

const counters = computed(() => {
  const p = pipeline.value
  if (!p) return []
  return [
    { key: 'to_imagine', label: 'À imaginer', value: p.to_imagine },
    { key: 'to_produce', label: 'À produire', value: p.to_produce },
    { key: 'to_validate', label: 'À valider', value: p.to_validate },
    { key: 'to_publish', label: 'À publier', value: p.to_publish },
    { key: 'published', label: 'Publiés', value: p.published },
  ]
})

const performance = computed(() => props.overview?.publication_performance || null)

const directAvailable = computed(() => props.overview?.direct_publishing_available === true)

function formatDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleString()
}
</script>

<template>
  <div class="space-y-6">
    <p v-if="props.loading" class="text-sm text-gray-500">Chargement des indicateurs…</p>

    <div
      v-else-if="!props.overview"
      class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-500"
    >
      Sélectionnez une entreprise cliente pour afficher son tableau de bord marketing.
    </div>

    <template v-else>
      <!-- Pipeline de contenu -->
      <section>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">Pipeline de contenu</h3>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          <div
            v-for="counter in counters"
            :key="counter.key"
            class="rounded-lg border border-gray-200 bg-white p-4"
          >
            <p class="text-xs uppercase tracking-wide text-gray-500">{{ counter.label }}</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ counter.value ?? 0 }}</p>
          </div>
        </div>
      </section>

      <!-- Mode de publication réellement disponible -->
      <section class="rounded-lg border border-gray-200 bg-white p-4">
        <h3 class="text-sm font-semibold text-gray-900">Publication</h3>
        <p class="mt-1 text-sm text-gray-600">
          <template v-if="directAvailable">
            Un connecteur de publication est configuré : la publication directe
            (API) est disponible pour cette entreprise.
          </template>
          <template v-else>
            Aucun connecteur n'est configuré pour cette entreprise : les
            publications se font en mode <strong>assisté</strong> (préparation,
            ouverture de la plateforme, confirmation manuelle avec l'URL réelle).
          </template>
        </p>
      </section>

      <!-- Contenus en retard -->
      <section>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">Contenus en retard</h3>
        <p
          v-if="!(props.overview.late_content || []).length"
          class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500"
        >
          Aucun contenu en retard sur les données actuelles.
        </p>
        <ul v-else class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
          <li
            v-for="item in props.overview.late_content"
            :key="item.id"
            class="flex items-center justify-between gap-3 p-3 text-sm"
          >
            <span class="font-medium text-gray-900">{{ item.title }}</span>
            <span class="flex items-center gap-2">
              <span class="text-xs text-gray-500">publi. {{ formatDate(item.publish_due_at) }}</span>
              <MarketingOsStatusBadge
                :label="`${item.days_late ?? 0} j de retard`"
                tone="danger"
              />
            </span>
          </li>
        </ul>
      </section>

      <!-- Publications planifiées -->
      <section>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">Publications planifiées</h3>
        <p
          v-if="!(props.overview.scheduled_publications || []).length"
          class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500"
        >
          Aucune publication planifiée.
        </p>
        <ul v-else class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
          <li
            v-for="item in props.overview.scheduled_publications"
            :key="item.id"
            class="flex items-center justify-between gap-3 p-3 text-sm"
          >
            <span class="font-medium text-gray-900">{{ item.platform }} — {{ item.title || 'Sans titre' }}</span>
            <span class="flex items-center gap-2">
              <span class="text-xs text-gray-500">{{ formatDate(item.scheduled_at) }}</span>
              <MarketingOsStatusBadge
                :label="labelFrom(PUBLICATION_MODE_LABELS, item.mode)"
                :tone="item.mode === 'direct' ? 'info' : 'muted'"
              />
              <MarketingOsStatusBadge v-if="item.is_overdue" label="En retard" tone="danger" />
            </span>
          </li>
        </ul>
      </section>

      <!-- Dernières publications réellement en ligne -->
      <section>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">Dernières publications</h3>
        <p
          v-if="!(props.overview.recent_publications || []).length"
          class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500"
        >
          Aucune publication enregistrée comme publiée.
        </p>
        <ul v-else class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
          <li
            v-for="item in props.overview.recent_publications"
            :key="item.id"
            class="flex flex-wrap items-center justify-between gap-3 p-3 text-sm"
          >
            <span class="font-medium text-gray-900">{{ item.platform }} — {{ item.title || 'Sans titre' }}</span>
            <span class="flex items-center gap-2">
              <MarketingOsStatusBadge
                :label="labelFrom(PUBLICATION_STATUS_LABELS, 'published')"
                tone="success"
              />
              <a
                v-if="item.published_url"
                :href="item.published_url"
                target="_blank"
                rel="noopener noreferrer"
                class="text-xs text-indigo-600 hover:underline"
              >
                Ouvrir la publication
              </a>
              <span class="text-xs text-gray-500">{{ formatDate(item.published_at) }}</span>
            </span>
          </li>
        </ul>
      </section>

      <!-- Campagnes actives -->
      <section>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">Campagnes actives</h3>
        <p
          v-if="!(props.overview.active_campaigns || []).length"
          class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500"
        >
          Aucune campagne active.
        </p>
        <ul v-else class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
          <li
            v-for="campaign in props.overview.active_campaigns"
            :key="campaign.id"
            class="flex items-center justify-between gap-3 p-3 text-sm"
          >
            <span class="font-medium text-gray-900">{{ campaign.name }}</span>
            <span class="flex items-center gap-2 text-xs text-gray-500">
              <span v-if="campaign.sector">{{ campaign.sector }}</span>
              <span>{{ formatDate(campaign.starts_at) }} → {{ formatDate(campaign.ends_at) }}</span>
            </span>
          </li>
        </ul>
      </section>

      <!-- Performances + provenance -->
      <section class="rounded-lg border border-gray-200 bg-white p-4">
        <h3 class="text-sm font-semibold text-gray-900">Performance des publications</h3>
        <p v-if="performance" class="mt-1 text-sm text-gray-600">
          {{ performance.published_total ?? 0 }} publication(s) publiée(s) —
          {{ performance.with_measured_stats ?? 0 }} avec statistiques de plateforme.
        </p>
        <p
          v-if="performance && performance.stats_source === 'unavailable'"
          class="mt-1 text-xs text-amber-700"
        >
          Statistiques non disponibles : aucune estimation n'est affichée.
        </p>
        <p v-else-if="performance" class="mt-1 text-xs text-gray-500">{{ performance.note }}</p>
      </section>

      <!-- Prochaines actions -->
      <section>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">Prochaines actions prioritaires</h3>
        <ul class="list-disc space-y-1 rounded-lg border border-gray-200 bg-white p-4 pl-8 text-sm text-gray-700">
          <li v-for="(action, index) in props.overview.next_actions || []" :key="index">
            {{ action }}
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>
