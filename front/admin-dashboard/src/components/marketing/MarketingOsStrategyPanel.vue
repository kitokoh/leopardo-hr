<script setup>
/**
 * Stratégie & calendrier éditorial (section B du brief).
 *
 * Deux vues d'un même jeu de données réel : un TABLEAU (toutes les colonnes de
 * la stratégie) et un CALENDRIER groupé par date de publication souhaitée. Il
 * n'y a pas de troisième source de vérité : les deux lisent `ideas`.
 *
 * « Transformer une idée en tâche de production » est une action serveur
 * idempotente : elle crée la tâche SANS perdre les informations saisies, et le
 * résultat (ou son échec) est toujours affiché à l'utilisateur.
 *
 * Garde PA2-I18N-014 : aucun libellé en dur — les tables d'énumération portent
 * des CLÉS i18n résolues par `$t()`. La clé technique de regroupement
 * « sans date » est une constante, jamais du texte affiché.
 */
import { computed, ref } from 'vue'
import MarketingOsStatusBadge from '@/components/marketing/MarketingOsStatusBadge.vue'
import {
  CONTENT_FORMAT_LABELS,
  CONTENT_IDEA_STATUS_LABELS,
  OBJECTIVE_LABELS,
  labelFrom,
} from '@/services/marketingOs'

const props = defineProps({
  ideas: { type: Array, default: () => [] },
  campaigns: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  canManage: { type: Boolean, default: false },
  actionMessage: { type: String, default: '' },
  actionError: { type: String, default: '' },
})

// `defineEmits` exige des littéraux inline (règle vue/valid-define-emits) :
// les noms d'événements sont donc écrits ici, une seule fois, et réutilisés
// ailleurs par le même littéral.
const emit = defineEmits(['create-idea', 'promote-idea'])

const NO_DATE_KEY = 'no-date'

const view = ref('table')

const STATUS_TONES = {
  idea: 'neutral',
  briefing: 'info',
  in_production: 'info',
  in_review: 'warning',
  approved: 'success',
  scheduled: 'info',
  published: 'success',
  cancelled: 'muted',
}

const emptyForm = () => ({
  title: '',
  campaign_id: '',
  objective: '',
  format: 'other',
  channel: '',
  locale: 'fr',
  sector: '',
  product: '',
  production_due_at: '',
  publish_due_at: '',
})

const showForm = ref(false)
const form = ref(emptyForm())

const campaignName = (id) => props.campaigns.find((c) => c.id === id)?.name || null

/** Calendrier : regroupement par date de publication souhaitée (réelle). */
const calendar = computed(() => {
  const buckets = new Map()
  props.ideas.forEach((idea) => {
    const key = idea.publish_due_at ? String(idea.publish_due_at).slice(0, 10) : NO_DATE_KEY
    if (!buckets.has(key)) buckets.set(key, [])
    buckets.get(key).push(idea)
  })
  return [...buckets.entries()]
    .sort(([a], [b]) => {
      if (a === NO_DATE_KEY) return 1
      if (b === NO_DATE_KEY) return -1
      return a.localeCompare(b)
    })
    .map(([date, items]) => ({ date, items }))
})

function formatDate(value) {
  if (!value) return null
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date.toLocaleDateString()
}

function submit() {
  emit('create-idea', {
    ...form.value,
    campaign_id: form.value.campaign_id === '' ? null : Number(form.value.campaign_id),
    objective: form.value.objective || null,
    channel: form.value.channel || null,
    sector: form.value.sector || null,
    product: form.value.product || null,
    production_due_at: form.value.production_due_at || null,
    publish_due_at: form.value.publish_due_at || null,
  })
  showForm.value = false
  form.value = emptyForm()
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="inline-flex rounded-md border border-gray-200 bg-white p-0.5">
        <button
          type="button"
          class="rounded px-3 py-1 text-sm"
          :class="view === 'table' ? 'bg-indigo-600 text-white' : 'text-gray-600'"
          @click="view = 'table'"
        >
          {{ $t('marketingOs.strategy.viewTable') }}
        </button>
        <button
          type="button"
          class="rounded px-3 py-1 text-sm"
          :class="view === 'calendar' ? 'bg-indigo-600 text-white' : 'text-gray-600'"
          @click="view = 'calendar'"
        >
          {{ $t('marketingOs.strategy.viewCalendar') }}
        </button>
      </div>
      <button
        v-if="props.canManage"
        type="button"
        class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500"
        @click="showForm = !showForm"
      >
        {{ showForm ? $t('marketingOs.strategy.cancel') : $t('marketingOs.strategy.newIdea') }}
      </button>
    </div>

    <p v-if="props.actionMessage" class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-800">
      {{ props.actionMessage }}
    </p>
    <p v-if="props.actionError" class="rounded-md bg-red-50 p-3 text-sm text-red-800">
      {{ props.actionError }}
    </p>

    <!-- Formulaire de création -->
    <form
      v-if="showForm"
      class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3"
      @submit.prevent="submit"
    >
      <label class="text-sm">
        <span class="font-medium text-gray-700">{{ $t('marketingOs.strategy.title') }}</span>
        <input v-model="form.title" required maxlength="180" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">{{ $t('marketingOs.strategy.campaign') }}</span>
        <select v-model="form.campaign_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
          <option value="">{{ $t('marketingOs.strategy.noCampaign') }}</option>
          <option v-for="c in props.campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">{{ $t('marketingOs.strategy.objective') }}</span>
        <select v-model="form.objective" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
          <option value="">{{ $t('marketingOs.strategy.noObjective') }}</option>
          <option v-for="(labelKey, value) in OBJECTIVE_LABELS" :key="value" :value="value">
            {{ $t(labelKey) }}
          </option>
        </select>
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">{{ $t('marketingOs.strategy.format') }}</span>
        <select v-model="form.format" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
          <option v-for="(labelKey, value) in CONTENT_FORMAT_LABELS" :key="value" :value="value">
            {{ $t(labelKey) }}
          </option>
        </select>
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">{{ $t('marketingOs.strategy.channel') }}</span>
        <input v-model="form.channel" maxlength="60" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">{{ $t('marketingOs.strategy.locale') }}</span>
        <input v-model="form.locale" maxlength="10" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">{{ $t('marketingOs.strategy.sector') }}</span>
        <input v-model="form.sector" maxlength="60" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">{{ $t('marketingOs.strategy.product') }}</span>
        <input v-model="form.product" maxlength="120" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">{{ $t('marketingOs.strategy.productionDue') }}</span>
        <input v-model="form.production_due_at" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">{{ $t('marketingOs.strategy.publishDue') }}</span>
        <input v-model="form.publish_due_at" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
      </label>
      <div class="flex items-end">
        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
          {{ $t('marketingOs.strategy.submit') }}
        </button>
      </div>
    </form>

    <p v-if="props.loading" class="text-sm text-gray-500">{{ $t('marketingOs.common.loading') }}</p>
    <p
      v-else-if="!props.ideas.length"
      class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-500"
    >
      {{ $t('marketingOs.strategy.empty') }}
    </p>

    <!-- Vue tableau -->
    <div v-else-if="view === 'table'" class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
          <tr>
            <th class="px-3 py-2">{{ $t('marketingOs.strategy.colTitle') }}</th>
            <th class="px-3 py-2">{{ $t('marketingOs.strategy.colCampaign') }}</th>
            <th class="px-3 py-2">{{ $t('marketingOs.strategy.colObjective') }}</th>
            <th class="px-3 py-2">{{ $t('marketingOs.strategy.colFormat') }}</th>
            <th class="px-3 py-2">{{ $t('marketingOs.strategy.colLocale') }}</th>
            <th class="px-3 py-2">{{ $t('marketingOs.strategy.colProduction') }}</th>
            <th class="px-3 py-2">{{ $t('marketingOs.strategy.colPublication') }}</th>
            <th class="px-3 py-2">{{ $t('marketingOs.strategy.colStatus') }}</th>
            <th class="px-3 py-2 text-right">{{ $t('marketingOs.strategy.colAction') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="idea in props.ideas" :key="idea.id">
            <td class="px-3 py-2 font-medium text-gray-900">{{ idea.title }}</td>
            <td class="px-3 py-2 text-gray-600">
              {{ campaignName(idea.campaign_id) || $t('marketingOs.common.dash') }}
            </td>
            <td class="px-3 py-2 text-gray-600">{{ $t(labelFrom(OBJECTIVE_LABELS, idea.objective)) }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $t(labelFrom(CONTENT_FORMAT_LABELS, idea.format)) }}</td>
            <td class="px-3 py-2 text-gray-600">{{ idea.locale }}</td>
            <td class="px-3 py-2 text-gray-600">
              {{ formatDate(idea.production_due_at) || $t('marketingOs.common.dash') }}
            </td>
            <td class="px-3 py-2 text-gray-600">
              {{ formatDate(idea.publish_due_at) || $t('marketingOs.common.dash') }}
            </td>
            <td class="px-3 py-2">
              <MarketingOsStatusBadge
                :label="$t(labelFrom(CONTENT_IDEA_STATUS_LABELS, idea.status))"
                :tone="STATUS_TONES[idea.status] || 'neutral'"
              />
            </td>
            <td class="px-3 py-2 text-right">
              <template v-if="idea.production_task_id">
                <span class="text-xs text-emerald-700">
                  {{ $t('marketingOs.strategy.taskCreated', { id: idea.production_task_id }) }}
                </span>
              </template>
              <button
                v-else-if="props.canManage"
                type="button"
                class="rounded border border-indigo-200 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50"
                @click="emit('promote-idea', idea)"
              >
                {{ $t('marketingOs.strategy.promote') }}
              </button>
              <span v-else class="text-xs text-gray-400">{{ $t('marketingOs.common.dash') }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Vue calendrier -->
    <div v-else class="space-y-3">
      <div
        v-for="bucket in calendar"
        :key="bucket.date"
        class="rounded-lg border border-gray-200 bg-white p-3"
      >
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
          {{ bucket.date === NO_DATE_KEY ? $t('marketingOs.strategy.noDate') : formatDate(bucket.date) }}
        </p>
        <ul class="mt-2 space-y-1">
          <li
            v-for="idea in bucket.items"
            :key="idea.id"
            class="flex items-center justify-between gap-2 text-sm"
          >
            <span class="text-gray-900">{{ idea.title }}</span>
            <span class="flex items-center gap-2">
              <MarketingOsStatusBadge
                :label="$t(labelFrom(CONTENT_FORMAT_LABELS, idea.format))"
                tone="muted"
              />
              <MarketingOsStatusBadge
                :label="$t(labelFrom(CONTENT_IDEA_STATUS_LABELS, idea.status))"
                :tone="STATUS_TONES[idea.status] || 'neutral'"
              />
            </span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
