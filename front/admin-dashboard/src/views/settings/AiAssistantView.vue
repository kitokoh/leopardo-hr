<template>
  <div class="space-y-8 animate-fade-in max-w-5xl">
    <!-- En-tête -->
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
        {{ t('aiAssistant.title') }}
      </h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        {{ t('aiAssistant.subtitle') }}
      </p>
    </div>

    <!-- Onglets -->
    <div class="flex gap-1 border-b border-slate-200 dark:border-slate-700">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        class="px-4 py-2 text-sm font-semibold -mb-px border-b-2 transition-colors"
        :class="
          activeTab === tab.id
            ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400'
            : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
        "
        @click="selectTab(tab.id)"
      >
        {{ t(tab.label) }}
      </button>
    </div>

    <!-- ══════════ CONFIGURATION ══════════ -->
    <template v-if="activeTab === 'config'">
      <!-- État réel : ce que le superviseur doit voir en premier -->
      <div
        class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-xl border p-4"
        :class="
          health.enabled
            ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/20'
            : 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20'
        "
      >
        <span class="text-sm font-semibold">
          {{ health.enabled ? t('aiAssistant.stateOn') : t('aiAssistant.stateOff') }}
        </span>
        <span class="text-sm">
          {{ t('aiAssistant.driver') }}: <strong>{{ health.driver || '—' }}</strong>
        </span>
        <span class="text-sm">
          {{ t('aiAssistant.model') }}: <strong>{{ health.model || '—' }}</strong>
        </span>
        <span class="text-sm">
          {{ t('aiAssistant.providerKey') }}:
          <strong>{{ health.provider_key_configured ? t('aiAssistant.yes') : t('aiAssistant.no') }}</strong>
        </span>
      </div>

      <p v-if="loadError" class="text-sm text-rose-600 dark:text-rose-400">{{ loadError }}</p>

      <!-- Un bloc par groupe du catalogue renvoyé par l'API -->
      <section
        v-for="(entries, group) in groupedSettings"
        :key="group"
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/70"
      >
        <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          {{ t(`aiAssistant.group_${group}`) }}
        </h2>

        <div class="space-y-5">
          <div v-for="(key, definition) in entries" :key="key">
            <label :for="`ai-${key}`" class="block text-sm font-semibold text-slate-800 dark:text-slate-200">
              {{ definition.label }}
            </label>

            <!-- Booléen -->
            <input
              v-if="definition.type === 'bool'"
              :id="`ai-${key}`"
              :checked="isChecked(key)"
              type="checkbox"
              @change="setField(key, $event)"
              class="mt-2 h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
            />

            <!-- Liste fermée -->
            <select
              v-else-if="definition.type === 'select'"
              :id="`ai-${key}`"
              :value="fieldValue(key)"
              class="mt-2 w-full max-w-md rounded-lg border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
              @change="setField(key, $event)"
            >
              <option v-for="option in definition.options || []" :key="option" :value="option">
                {{ option }}
              </option>
            </select>

            <!-- Texte / secret -->
            <input
              v-else
              :id="`ai-${key}`"
              :value="fieldValue(key)"
              type="text"
              autocomplete="off"
              class="mt-2 w-full max-w-md rounded-lg border-slate-300 bg-white font-mono text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
              :placeholder="placeholderFor(key, definition)"
              @input="setField(key, $event)"
            />

            <p v-if="definition.secret" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
              <span :class="statusClass(key)">
                {{ configured(key) ? t('aiAssistant.keyConfigured') : t('aiAssistant.keyNotConfigured') }}
              </span>
              — {{ t('aiAssistant.secretHint') }}
            </p>
            <p v-else-if="definition.help" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
              {{ definition.help }}
            </p>
          </div>
        </div>
      </section>

      <!-- Actions -->
      <div class="flex flex-wrap items-center gap-3">
        <button
          type="button"
          class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
          :disabled="saving"
          @click="save"
        >
          {{ saving ? t('aiAssistant.saving') : t('aiAssistant.save') }}
        </button>
        <button
          type="button"
          class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
          :disabled="testing"
          @click="testConnection"
        >
          {{ testing ? t('aiAssistant.testing') : t('aiAssistant.test') }}
        </button>
      </div>

      <!-- Résultat du test : explicite, jamais un simple « erreur » -->
      <div
        v-if="testResult"
        class="rounded-xl border p-4 text-sm"
        :class="
          testResult.ok
            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-200'
            : 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-900/20 dark:text-rose-200'
        "
      >
        {{ testResult.message }}
      </div>
    </template>

    <!-- ══════════ SUIVI ══════════ -->
    <template v-else>
      <div class="flex items-center gap-3">
        <label class="text-sm font-medium text-slate-600 dark:text-slate-300" for="ai-period">
          {{ t('aiAssistant.period') }}
        </label>
        <select
          id="ai-period"
          v-model.number="periodDays"
          class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
          @change="loadMonitoring"
        >
          <option :value="7">{{ t('aiAssistant.last7') }}</option>
          <option :value="30">{{ t('aiAssistant.last30') }}</option>
          <option :value="90">{{ t('aiAssistant.last90') }}</option>
        </select>
      </div>

      <!-- Aucune donnée : état vide honnête, pas un zéro trompeur -->
      <div
        v-if="!monitoringLoading && totals.requests === 0"
        class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-500 dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-400"
      >
        {{ t('aiAssistant.noData') }}
      </div>

      <template v-else>
        <!-- Indicateurs -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
          <div
            v-for="kpi in kpis"
            :key="kpi.label"
            class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900/70"
          >
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              {{ kpi.label }}
            </p>
            <p class="mt-1 text-2xl font-black text-slate-900 dark:text-white">{{ kpi.value }}</p>
          </div>
        </div>

        <!-- Par entreprise -->
        <section v-if="byTenant.length" class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900/70">
          <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            {{ t('aiAssistant.byTenant') }}
          </h2>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-xs uppercase text-slate-400">
                  <th class="py-2">{{ t('aiAssistant.company') }}</th>
                  <th class="py-2 text-right">{{ t('aiAssistant.requests') }}</th>
                  <th class="py-2 text-right">{{ t('aiAssistant.tokens') }}</th>
                  <th class="py-2 text-right">{{ t('aiAssistant.errors') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in byTenant" :key="row.company_id" class="border-t border-slate-100 dark:border-slate-800">
                  <td class="py-2 text-slate-700 dark:text-slate-200">{{ row.company_name || row.company_id }}</td>
                  <td class="py-2 text-right tabular-nums">{{ row.requests }}</td>
                  <td class="py-2 text-right tabular-nums">{{ row.tokens }}</td>
                  <td class="py-2 text-right tabular-nums" :class="errorClass(row.errors)">
                    {{ row.errors }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Par outil -->
        <section v-if="byTool.length" class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900/70">
          <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            {{ t('aiAssistant.byTool') }}
          </h2>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-xs uppercase text-slate-400">
                  <th class="py-2">{{ t('aiAssistant.tool') }}</th>
                  <th class="py-2 text-right">{{ t('aiAssistant.calls') }}</th>
                  <th class="py-2 text-right">{{ t('aiAssistant.awaitingConfirmation') }}</th>
                  <th class="py-2 text-right">{{ t('aiAssistant.failures') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in byTool" :key="row.tool_name" class="border-t border-slate-100 dark:border-slate-800">
                  <td class="py-2 font-mono text-xs text-slate-700 dark:text-slate-200">{{ row.tool_name }}</td>
                  <td class="py-2 text-right tabular-nums">{{ row.calls }}</td>
                  <td class="py-2 text-right tabular-nums">{{ row.awaiting_confirmation }}</td>
                  <td class="py-2 text-right tabular-nums" :class="errorClass(row.failures)">
                    {{ row.failures }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Erreurs récentes -->
        <section v-if="recentErrors.length" class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900/70">
          <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            {{ t('aiAssistant.recentErrors') }}
          </h2>
          <ul class="space-y-3">
            <li v-for="(err, index) in recentErrors" :key="index" class="text-sm">
              <span class="font-mono text-xs text-slate-400">{{ err.created_at }}</span>
              <span class="ml-2 rounded bg-slate-100 px-2 py-0.5 font-mono text-xs dark:bg-slate-800">
                {{ err.provider || '—' }}
              </span>
              <p class="mt-1 text-rose-700 dark:text-rose-300">{{ err.error }}</p>
            </li>
          </ul>
        </section>
      </template>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
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
const toast = useToast()

const BASE = '/admin/platform/ai'

const tabs = [
  { id: 'config', label: 'aiAssistant.tabConfig' },
  { id: 'monitoring', label: 'aiAssistant.tabMonitoring' },
]
const activeTab = ref('config')

const catalog = ref({})
const settings = ref({})
const health = ref({})
const form = reactive({})
const loadError = ref('')

const saving = ref(false)
const testing = ref(false)
const testResult = ref(null)

const monitoring = ref(null)
const monitoringLoading = ref(false)
const periodDays = ref(30)

const groupedSettings = computed(() => {
  const groups = {}
  for (const [key, definition] of Object.entries(catalog.value)) {
    const group = definition.group || 'general'
    if (!groups[group]) groups[group] = []
    groups[group].push([key, definition])
  }
  return groups
})

/** Un secret enregistré n'est jamais pré-rempli : le champ vide = « conserver ». */
function placeholderFor(key, definition) {
  if (definition.secret) {
    return configured(key) ? t('aiAssistant.secretPlaceholder') : ''
  }
  return ''
}

function configured(key) {
  return Boolean(settings.value[key]?.has_value)
}

/** Lecture du formulaire pour un champ à clé dynamique (équivalent de v-model). */
function fieldValue(key) {
  return form[key] ?? ''
}

function isChecked(key) {
  return Boolean(form[key])
}

/** Un seul gestionnaire pour les trois types de champ : booléen, liste, texte. */
function setField(key, event) {
  const target = event.target
  form[key] = target.type === 'checkbox' ? target.checked : target.value
}

function selectTab(id) {
  activeTab.value = id
}

function statusClass(key) {
  return configured(key) ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'
}

function errorClass(value) {
  return value ? 'text-rose-600 dark:text-rose-400' : ''
}

async function load() {
  loadError.value = ''
  try {
    const { data } = await api.get(`${BASE}/settings`)
    catalog.value = data?.data?.catalog || {}
    settings.value = data?.data?.settings || {}

    for (const [key, definition] of Object.entries(catalog.value)) {
      const stored = settings.value[key]?.value
      // Un booléen stocké arrive en '1'/'0' (chaîne) → on normalise en booléen.
      form[key] = definition.type === 'bool' ? stored === '1' || stored === true : stored || ''
    }
  } catch (e) {
    loadError.value = t('aiAssistant.loadError')
    console.warn('[admin] ai settings load failed', e)
  }
}

async function loadHealth() {
  try {
    const { data } = await api.get(`${BASE}/health`)
    health.value = data?.data || {}
  } catch (e) {
    console.warn('[admin] ai health load failed', e)
  }
}

async function loadMonitoring() {
  monitoringLoading.value = true
  try {
    const to = new Date()
    const from = new Date(to.getTime() - periodDays.value * 24 * 3600 * 1000)
    const iso = (d) => d.toISOString().slice(0, 10)
    const { data } = await api.get(`${BASE}/monitoring`, {
      params: { from: iso(from), to: iso(to) },
    })
    monitoring.value = data?.data || null
  } catch (e) {
    console.warn('[admin] ai monitoring load failed', e)
  } finally {
    monitoringLoading.value = false
  }
}

async function save() {
  saving.value = true
  testResult.value = null
  try {
    const payload = {}
    for (const key of Object.keys(catalog.value)) {
      const definition = catalog.value[key]
      const value = form[key]
      if (definition.type === 'bool') {
        payload[key] = value ? '1' : '0'
      } else {
        // Champ vide sur un secret = « conserver la clé enregistrée », on
        // n'envoie donc rien (l'API préserve explicitement l'existant).
        if (definition.secret && !String(value || '').trim()) continue
        payload[key] = String(value ?? '')
      }
    }
    await api.put(`${BASE}/settings`, { settings: payload })
    toast.success(t('aiAssistant.saved'))
    await load()
    await loadHealth()
  } catch (e) {
    console.warn('[admin] ai settings save failed', e)
  } finally {
    saving.value = false
  }
}

async function testConnection() {
  testing.value = true
  testResult.value = null
  try {
    const { data } = await api.post(`${BASE}/test-connection`)
    testResult.value = { ok: true, message: data?.data?.message || t('aiAssistant.testOk') }
  } catch (e) {
    const message = e?.response?.data?.message || t('aiAssistant.testFailed')
    testResult.value = { ok: false, message }
  } finally {
    testing.value = false
  }
}

const totals = computed(() => monitoring.value?.totals || { requests: 0 })
const byTenant = computed(() => monitoring.value?.by_tenant || [])
const byTool = computed(() => monitoring.value?.by_tool || [])
const recentErrors = computed(() => monitoring.value?.recent_errors || [])

const kpis = computed(() => [
  { label: t('aiAssistant.requests'), value: totals.value.requests ?? 0 },
  { label: t('aiAssistant.tokens'), value: (totals.value.input_tokens ?? 0) + (totals.value.output_tokens ?? 0) },
  { label: t('aiAssistant.cost'), value: ((totals.value.cost_cents ?? 0) / 100).toFixed(2) },
  { label: t('aiAssistant.errors'), value: totals.value.errors ?? 0 },
  { label: t('aiAssistant.errorRate'), value: `${totals.value.error_rate ?? 0} %` },
  { label: t('aiAssistant.p95'), value: `${totals.value.p95_duration_ms ?? 0} ms` },
])

onMounted(async () => {
  await load()
  await loadHealth()
  await loadMonitoring()
})
</script>
