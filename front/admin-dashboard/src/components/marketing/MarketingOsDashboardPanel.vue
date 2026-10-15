<script setup>
/**
 * Tableau de bord Marketing OS (section A du brief).
 *
 * Chaque indicateur vient d'une DONNÉE RÉELLE renvoyée par le serveur. Deux
 * compteurs d'« à produire / à valider » sont calculés ici par addition de
 * statuts renvoyés par l'API — jamais inventés. La performance de publication
 * affiche explicitement sa provenance : si aucune plateforme n'a fourni de
 * statistiques, l'écran écrit « non disponible », jamais « 0 ».
 *
 * Garde PA2-I18N-014 : tous les libellés passent par le catalogue i18n. Les
 * listes `next_actions` et `late_content` sont rédigées par le SERVEUR (elles
 * ne sont donc pas concernées par la garde front) et sont affichées telles quelles.
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

// Chaque compteur porte une CLÉ i18n, pas un libellé.
const counters = computed(() => {
  const p = pipeline.value
  if (!p) return []
  return [
    { key: 'to_imagine', labelKey: 'marketingOs.counter.toImagine', value: p.to_imagine },
    { key: 'to_produce', labelKey: 'marketingOs.counter.toProduce', value: p.to_produce },
    { key: 'to_validate', labelKey: 'marketingOs.counter.toValidate', value: p.to_validate },
    { key: 'to_publish', labelKey: 'marketingOs.counter.toPublish', value: p.to_publish },
    { key: 'published', labelKey: 'marketingOs.counter.published', value: p.published },
  ]
})

const performance = computed(() => props.overview?.publication_performance || null)

const directAvailable = computed(() => props.overview?.direct_publishing_available === true)

function formatDate(value) {
  if (!value) return null
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date.toLocaleString()
}
</script>

<template>
  <div class="space-y-6">
    <p v-if="props.loading" class="text-sm text-gray-500">{{ $t('marketingOs.dashboard.loading') }}</p>

    <div
      v-else-if="!props.overview"
      class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-500"
    >
      {{ $t('marketingOs.dashboard.empty') }}
    </div>

    <template v-else>
      <!-- Pipeline de contenu -->
      <section>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">{{ $t('marketingOs.dashboard.pipeline') }}</h3>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          <div
            v-for="counter in counters"
            :key="counter.key"
            class="rounded-lg border border-gray-200 bg-white p-4"
          >
            <p class="text-xs uppercase tracking-wide text-gray-500">{{ $t(counter.labelKey) }}</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ counter.value ?? 0 }}</p>
          </div>
        </div>
      </section>

      <!-- Mode de publication réellement disponible -->
      <section class="rounded-lg border border-gray-200 bg-white p-4">
        <h3 class="text-sm font-semibold text-gray-900">{{ $t('marketingOs.dashboard.publishing') }}</h3>
        <p class="mt-1 text-sm text-gray-600">
          {{ directAvailable ? $t('marketingOs.dashboard.directAvailable') : $t('marketingOs.dashboard.assistedOnly') }}
        </p>
      </section>

      <!-- Contenus en retard -->
      <section>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">{{ $t('marketingOs.dashboard.lateContent') }}</h3>
        <p
          v-if="!(props.overview.late_content || []).length"
          class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500"
        >
          {{ $t('marketingOs.dashboard.noLateContent') }}
        </p>
        <ul v-else class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
          <li
            v-for="item in props.overview.late_content"
            :key="item.id"
            class="flex items-center justify-between gap-3 p-3 text-sm"
          >
            <span class="font-medium text-gray-900">{{ item.title }}</span>
            <span class="flex items-center gap-2">
              <span class="text-xs text-gray-500">
                {{ formatDate(item.publish_due_at) || $t('marketingOs.common.dash') }}
              </span>
              <MarketingOsStatusBadge
                :label="$t('marketingOs.dashboard.daysLate', { count: item.days_late ?? 0 })"
                tone="danger"
              />
            </span>
          </li>
        </ul>
      </section>

      <!-- Publications planifiées -->
      <section>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">{{ $t('marketingOs.dashboard.scheduled') }}</h3>
        <p
          v-if="!(props.overview.scheduled_publications || []).length"
          class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500"
        >
          {{ $t('marketingOs.dashboard.noScheduled') }}
        </p>
        <ul v-else class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
          <li
            v-for="item in props.overview.scheduled_publications"
            :key="item.id"
            class="flex items-center justify-between gap-3 p-3 text-sm"
          >
            <span class="font-medium text-gray-900">
              {{ item.platform }} — {{ item.title || $t('marketingOs.common.untitled') }}
            </span>
            <span class="flex items-center gap-2">
              <span class="text-xs text-gray-500">
                {{ formatDate(item.scheduled_at) || $t('marketingOs.common.dash') }}
              </span>
              <MarketingOsStatusBadge
                :label="$t(labelFrom(PUBLICATION_MODE_LABELS, item.mode))"
                :tone="item.mode === 'direct' ? 'info' : 'muted'"
              />
              <MarketingOsStatusBadge
                v-if="item.is_overdue"
                :label="$t('marketingOs.dashboard.overdue')"
                tone="danger"
              />
            </span>
          </li>
        </ul>
      </section>

      <!-- Dernières publications réellement en ligne -->
      <section>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">{{ $t('marketingOs.dashboard.recent') }}</h3>
        <p
          v-if="!(props.overview.recent_publications || []).length"
          class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500"
        >
          {{ $t('marketingOs.dashboard.noRecent') }}
        </p>
        <ul v-else class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
          <li
            v-for="item in props.overview.recent_publications"
            :key="item.id"
            class="flex flex-wrap items-center justify-between gap-3 p-3 text-sm"
          >
            <span class="font-medium text-gray-900">
              {{ item.platform }} — {{ item.title || $t('marketingOs.common.untitled') }}
            </span>
            <span class="flex items-center gap-2">
              <MarketingOsStatusBadge
                :label="$t(labelFrom(PUBLICATION_STATUS_LABELS, 'published'))"
                tone="success"
              />
              <a
                v-if="item.published_url"
                :href="item.published_url"
                target="_blank"
                rel="noopener noreferrer"
                class="text-xs text-indigo-600 hover:underline"
              >
                {{ $t('marketingOs.dashboard.openPublication') }}
              </a>
              <span class="text-xs text-gray-500">
                {{ formatDate(item.published_at) || $t('marketingOs.common.dash') }}
              </span>
            </span>
          </li>
        </ul>
      </section>

      <!-- Campagnes actives -->
      <section>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">{{ $t('marketingOs.dashboard.activeCampaigns') }}</h3>
        <p
          v-if="!(props.overview.active_campaigns || []).length"
          class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500"
        >
          {{ $t('marketingOs.dashboard.noActiveCampaigns') }}
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
              <span>
                {{ formatDate(campaign.starts_at) || $t('marketingOs.common.dash') }}
                →
                {{ formatDate(campaign.ends_at) || $t('marketingOs.common.dash') }}
              </span>
            </span>
          </li>
        </ul>
      </section>

      <!-- Performances + provenance -->
      <section class="rounded-lg border border-gray-200 bg-white p-4">
        <h3 class="text-sm font-semibold text-gray-900">{{ $t('marketingOs.dashboard.performance') }}</h3>
        <p v-if="performance" class="mt-1 text-sm text-gray-600">
          {{
            $t('marketingOs.dashboard.performanceSummary', {
              published: performance.published_total ?? 0,
              measured: performance.with_measured_stats ?? 0,
            })
          }}
        </p>
        <p
          v-if="performance && performance.stats_source === 'unavailable'"
          class="mt-1 text-xs text-amber-700"
        >
          {{ $t('marketingOs.dashboard.statsUnavailable') }}
        </p>
        <p v-else-if="performance" class="mt-1 text-xs text-gray-500">{{ performance.note }}</p>
      </section>

      <!-- Prochaines actions -->
      <section>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">{{ $t('marketingOs.dashboard.nextActions') }}</h3>
        <ul class="list-disc space-y-1 rounded-lg border border-gray-200 bg-white p-4 pl-8 text-sm text-gray-700">
          <li v-for="(action, index) in props.overview.next_actions || []" :key="index">
            {{ action }}
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>
