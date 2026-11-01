<template>
  <div class="space-y-8 animate-fade-in max-w-3xl">
    <!-- En-tête -->
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
        {{ t('discoverySettings.title') }}
      </h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        {{ t('discoverySettings.subtitle') }}
      </p>
    </div>

    <!-- Échec de chargement ≠ valeur par défaut : on ne montre JAMAIS un
         formulaire pré-rempli à 5 quand la lecture a échoué — le super admin
         enregistrerait 5 en croyant conserver la valeur courante. -->
    <div
      v-if="loadError"
      class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200"
    >
      <p class="font-semibold">{{ t('discoverySettings.loadError') }}</p>
      <button
        type="button"
        class="mt-2 text-sm font-semibold underline"
        @click="load"
      >
        {{ t('discoverySettings.retry') }}
      </button>
    </div>

    <template v-else>
      <!-- Squelette de chargement -->
      <div
        v-if="loading"
        class="space-y-4 animate-pulse"
        data-testid="discovery-settings-skeleton"
      >
        <div class="h-4 w-1/3 rounded bg-slate-200 dark:bg-slate-700" />
        <div class="h-12 w-full rounded bg-slate-200 dark:bg-slate-700" />
        <div class="h-4 w-2/3 rounded bg-slate-200 dark:bg-slate-700" />
      </div>

      <template v-else>
        <!-- Carte principale -->
        <section
          class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"
        >
          <label
            for="category-min-pages"
            class="block text-sm font-bold text-slate-900 dark:text-white"
          >
            {{ t('discoverySettings.thresholdLabel') }}
          </label>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {{ t('discoverySettings.thresholdHint', { defaultValue: effective }) }}
          </p>

          <div class="mt-4 flex items-center gap-3">
            <input
              id="category-min-pages"
              v-model.number="form.category_min_pages"
              type="number"
              :min="min"
              :max="max"
              step="1"
              inputmode="numeric"
              class="w-32 rounded-xl border border-slate-300 px-4 py-2 text-lg font-semibold tabular-nums text-slate-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
              :aria-invalid="validationError ? 'true' : 'false'"
              aria-describedby="category-min-pages-help"
              @keydown.enter.prevent="save"
            />
            <span class="text-sm text-slate-500 dark:text-slate-400">
              {{ t('discoverySettings.pages') }}
            </span>
          </div>

          <!-- Erreur de validation : on explique POURQUOI, on ne corrige pas
               en silence. -->
          <p
            v-if="validationError"
            id="category-min-pages-help"
            class="mt-2 text-sm font-semibold text-rose-600 dark:text-rose-400"
          >
            {{ validationError }}
          </p>
          <p
            v-else
            id="category-min-pages-help"
            class="mt-2 text-sm text-slate-500 dark:text-slate-400"
          >
            {{ t('discoverySettings.minMax', { min, max }) }}
          </p>

          <!-- Seuils par ville posés hors interface : les montrer, sinon
               l'écran afficherait 5 alors que Paris tourne à 8. -->
          <div
            v-if="cityOverrides.length"
            class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/30"
          >
            <p class="text-sm font-bold text-amber-900 dark:text-amber-200">
              {{ t('discoverySettings.cityOverridesTitle') }}
            </p>
            <ul class="mt-2 space-y-1 text-sm text-amber-800 dark:text-amber-300">
              <li v-for="entry in cityOverrides" :key="entry.city" class="tabular-nums">
                {{ entry.city }} — {{ entry.value }} {{ t('discoverySettings.pages') }}
              </li>
            </ul>
            <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">
              {{ t('discoverySettings.cityOverridesHint') }}
            </p>
          </div>

          <!-- Actions -->
          <div class="mt-6 flex items-center gap-3">
            <button
              type="button"
              :disabled="saving || !isDirty"
              class="rounded-xl bg-emerald-600 px-5 py-2 font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300 dark:disabled:bg-slate-700"
              @click="save"
            >
              {{ saving ? t('discoverySettings.saving') : t('discoverySettings.save') }}
            </button>
            <button
              v-if="isDirty"
              type="button"
              class="text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400"
              @click="reset"
            >
              {{ t('discoverySettings.cancel') }}
            </button>
            <!-- Rien à enregistrer : on le dit, plutôt que de laisser un
                 bouton mort qui ne fait rien de visible. -->
            <span
              v-else-if="!saving"
              class="text-sm text-slate-400"
            >
              {{ t('discoverySettings.noChange') }}
            </span>
          </div>
        </section>

        <!-- Résultat de l'enregistrement : succès avec l'ancienne ET la
             nouvelle valeur, pour que le changement soit lisible. -->
        <div
          v-if="saveResult"
          class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
          role="status"
        >
          <p class="font-semibold">
            {{ t('discoverySettings.savedFrom', { previous: saveResult.previous, current: saveResult.current }) }}
          </p>
          <p class="mt-1 text-sm">
            {{ t('discoverySettings.audited') }}
          </p>
        </div>

        <div
          v-if="saveError"
          class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200"
          role="alert"
        >
          {{ saveError }}
        </div>
      </template>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, vars = {}) => {
  let msg = translate(localeStore.current, key) || key
  for (const [k, v] of Object.entries(vars)) {
    msg = msg.replace(`{${k}}`, String(v)).replace(`{{ ${k} }}`, String(v))
  }
  return msg
}

const BASE = '/admin/platform/discovery/settings'

const loading = ref(true)
const loadError = ref('')
const saving = ref(false)
const saveError = ref('')
const saveResult = ref(null)

const form = reactive({ category_min_pages: 5 })
const persisted = ref(5)
const effective = ref(5)
const min = ref(1)
const max = ref(1000)
const byCity = ref({})

const cityOverrides = computed(() =>
  Object.entries(byCity.value || {})
    .filter(([, value]) => Number.isFinite(Number(value)))
    .map(([city, value]) => ({ city, value: Number(value) })),
)

const isDirty = computed(() => Number(form.category_min_pages) !== persisted.value)

/**
 * Validation côté client pour un retour immédiat — le serveur revalide.
 *
 * On ne « corrige » jamais la saisie : 0 est refusé avec un message, pas
 * remplacé par 5. Corriger en silence ferait croire au super admin que sa
 * valeur a été appliquée.
 */
const validationError = computed(() => {
  const value = form.category_min_pages
  if (value === '' || value === null || value === undefined) {
    return t('discoverySettings.error.required')
  }
  const numeric = Number(value)
  if (!Number.isFinite(numeric)) return t('discoverySettings.error.required')
  if (!Number.isInteger(numeric)) return t('discoverySettings.error.integer')
  if (numeric < min.value) return t('discoverySettings.error.min', { min: min.value })
  if (numeric > max.value) return t('discoverySettings.error.max', { max: max.value })
  return ''
})

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get(BASE)
    const payload = data?.data || {}
    const settings = payload.settings || {}
    const key = payload.key || 'discovery.category_min_pages'
    const entry = settings[key] || {}

    persisted.value = Number(entry.value ?? payload.effective ?? 5)
    effective.value = Number(payload.effective ?? persisted.value)
    min.value = Number(payload.min ?? 1)
    max.value = Number(payload.max ?? 1000)
    byCity.value = entry.by_city || {}
    form.category_min_pages = persisted.value
  } catch (e) {
    // Jamais de formulaire pré-rempli après un échec de lecture : le super
    // admin enregistrerait une valeur qu'il n'a pas choisie.
    loadError.value = t('discoverySettings.loadError')
    console.warn('[admin] discovery settings load failed', e)
  } finally {
    loading.value = false
  }
}

function reset() {
  form.category_min_pages = persisted.value
  saveError.value = ''
}

async function save() {
  saveError.value = ''
  saveResult.value = null

  if (validationError.value) {
    saveError.value = validationError.value
    return
  }

  saving.value = true
  try {
    const { data } = await api.put(BASE, {
      category_min_pages: Number(form.category_min_pages),
    })
    saveResult.value = data?.data || null
    await load()
  } catch (e) {
    const message =
      e?.response?.data?.message ||
      t('discoverySettings.saveError')
    saveError.value = message
    console.warn('[admin] discovery settings save failed', e)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
