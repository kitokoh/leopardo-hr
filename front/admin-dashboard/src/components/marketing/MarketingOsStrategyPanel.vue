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

const emit = defineEmits(['create-idea', 'promote-idea'])

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

const showForm = ref(false)
const form = ref({
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

const campaignName = (id) => props.campaigns.find((c) => c.id === id)?.name || null

/** Calendrier : regroupement par date de publication souhaitée (réelle). */
const calendar = computed(() => {
  const buckets = new Map()
  props.ideas.forEach((idea) => {
    const key = idea.publish_due_at ? String(idea.publish_due_at).slice(0, 10) : 'sans-date'
    if (!buckets.has(key)) buckets.set(key, [])
    buckets.get(key).push(idea)
  })
  return [...buckets.entries()]
    .sort(([a], [b]) => (a === 'sans-date' ? 1 : b === 'sans-date' ? -1 : a.localeCompare(b)))
    .map(([date, items]) => ({ date, items }))
})

function formatDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleDateString()
}

function submit() {
  emit('create-idea', {
    ...props.form,
    campaign_id: props.form.campaign_id === '' ? null : Number(props.form.campaign_id),
    objective: props.form.objective || null,
    channel: props.form.channel || null,
    sector: props.form.sector || null,
    product: props.form.product || null,
    production_due_at: props.form.production_due_at || null,
    publish_due_at: props.form.publish_due_at || null,
  })
  showForm.value = false
  form.value = { title: '', campaign_id: '', objective: '', format: 'other', channel: '', locale: 'fr', sector: '', product: '', production_due_at: '', publish_due_at: '' }
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
          Tableau
        </button>
        <button
          type="button"
          class="rounded px-3 py-1 text-sm"
          :class="view === 'calendar' ? 'bg-indigo-600 text-white' : 'text-gray-600'"
          @click="view = 'calendar'"
        >
          Calendrier
        </button>
      </div>
      <button
        v-if="props.canManage"
        type="button"
        class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500"
        @click="showForm = !showForm"
      >
        {{ showForm ? 'Annuler' : 'Nouvelle idée' }}
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
        <span class="font-medium text-gray-700">Titre *</span>
        <input v-model="form.title" required maxlength="180" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">Campagne</span>
        <select v-model="form.campaign_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
          <option value="">— Aucune —</option>
          <option v-for="c in props.campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">Objectif</span>
        <select v-model="form.objective" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
          <option value="">— Non précisé —</option>
          <option v-for="(label, value) in OBJECTIVE_LABELS" :key="value" :value="value">{{ label }}</option>
        </select>
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">Format</span>
        <select v-model="form.format" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
          <option v-for="(label, value) in CONTENT_FORMAT_LABELS" :key="value" :value="value">{{ label }}</option>
        </select>
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">Canal</span>
        <input v-model="form.channel" maxlength="60" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">Langue</span>
        <input v-model="form.locale" maxlength="10" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">Secteur / verticale</span>
        <input v-model="form.sector" maxlength="60" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">Produit mis en avant</span>
        <input v-model="form.product" maxlength="120" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">Production souhaitée</span>
        <input v-model="form.production_due_at" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
      </label>
      <label class="text-sm">
        <span class="font-medium text-gray-700">Publication souhaitée</span>
        <input v-model="form.publish_due_at" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
      </label>
      <div class="flex items-end">
        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
          Créer l'idée
        </button>
      </div>
    </form>

    <p v-if="props.loading" class="text-sm text-gray-500">Chargement…</p>
    <p
      v-else-if="!props.ideas.length"
      class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-500"
    >
      Aucune idée de contenu pour cette entreprise.
    </p>

    <!-- Vue tableau -->
    <div v-else-if="view === 'table'" class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
          <tr>
            <th class="px-3 py-2">Titre</th>
            <th class="px-3 py-2">Campagne</th>
            <th class="px-3 py-2">Objectif</th>
            <th class="px-3 py-2">Format</th>
            <th class="px-3 py-2">Langue</th>
            <th class="px-3 py-2">Production</th>
            <th class="px-3 py-2">Publication</th>
            <th class="px-3 py-2">Statut</th>
            <th class="px-3 py-2 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="idea in props.ideas" :key="idea.id">
            <td class="px-3 py-2 font-medium text-gray-900">{{ idea.title }}</td>
            <td class="px-3 py-2 text-gray-600">{{ campaignName(idea.campaign_id) || '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ labelFrom(OBJECTIVE_LABELS, idea.objective) }}</td>
            <td class="px-3 py-2 text-gray-600">{{ labelFrom(CONTENT_FORMAT_LABELS, idea.format) }}</td>
            <td class="px-3 py-2 text-gray-600">{{ idea.locale }}</td>
            <td class="px-3 py-2 text-gray-600">{{ formatDate(idea.production_due_at) }}</td>
            <td class="px-3 py-2 text-gray-600">{{ formatDate(idea.publish_due_at) }}</td>
            <td class="px-3 py-2">
              <MarketingOsStatusBadge
                :label="labelFrom(CONTENT_IDEA_STATUS_LABELS, idea.status)"
                :tone="STATUS_TONES[idea.status] || 'neutral'"
              />
            </td>
            <td class="px-3 py-2 text-right">
              <template v-if="idea.production_task_id">
                <span class="text-xs text-emerald-700">Tâche #{{ idea.production_task_id }}</span>
              </template>
              <button
                v-else-if="props.canManage"
                type="button"
                class="rounded border border-indigo-200 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50"
                @click="emit('promote-idea', idea)"
              >
                Passer en production
              </button>
              <span v-else class="text-xs text-gray-400">—</span>
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
          {{ bucket.date === 'sans-date' ? 'Sans date de publication' : formatDate(bucket.date) }}
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
                :label="labelFrom(CONTENT_FORMAT_LABELS, idea.format)"
                tone="muted"
              />
              <MarketingOsStatusBadge
                :label="labelFrom(CONTENT_IDEA_STATUS_LABELS, idea.status)"
                :tone="STATUS_TONES[idea.status] || 'neutral'"
              />
            </span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
