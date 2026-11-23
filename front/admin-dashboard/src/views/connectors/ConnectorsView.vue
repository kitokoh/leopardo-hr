<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
          {{ t('connectors.title') }}
        </h1>
        <p class="mt-1 max-w-3xl text-sm text-slate-500 dark:text-slate-400">
          {{ t('connectors.subtitle') }}
        </p>
      </div>
      <button class="btn-primary" @click="openCreate">
        <PlusIcon class="mr-2 h-4 w-4" />
        {{ t('connectors.addConnection') }}
      </button>
    </div>

    <div v-if="loading" class="glass-card p-6 text-sm text-slate-500 dark:text-slate-400">
      {{ t('connectors.loading') }}
    </div>
    <div v-else-if="error" class="glass-card p-6 text-sm text-red-600 dark:text-red-400">
      {{ error }}
    </div>

    <template v-else>
      <!-- ── Connexions de la plateforme ───────────────────────────────── -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
            {{ t('connectors.platformConnections') }}
          </h2>
          <span class="text-xs text-slate-500 dark:text-slate-400">
            {{ t('connectors.activeCount', { active: activeCount, total: connections.length }) }}
          </span>
        </div>

        <div
          v-if="!connections.length"
          class="glass-card p-6 text-sm text-slate-500 dark:text-slate-400"
        >
          {{ t('connectors.emptyConnections') }}
        </div>

        <div v-else class="grid grid-cols-1 gap-4 xl:grid-cols-2">
          <article
            v-for="connection in connections"
            :key="connection.id"
            class="glass-card p-5"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="truncate text-base font-semibold text-slate-900 dark:text-white">
                    {{ connection.label }}
                  </h3>
                  <span
                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="statusBadgeClass(connection.status)"
                  >
                    {{ t(`connectors.status.${connection.status}`) }}
                  </span>
                  <span
                    v-if="connection.is_default"
                    class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300"
                  >
                    {{ t('connectors.default') }}
                  </span>
                </div>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  {{ catalogLabel(connection.catalog_key) }}
                </p>
              </div>

              <div class="flex shrink-0 items-center gap-2">
                <button
                  class="btn-secondary"
                  :disabled="testingId === connection.id"
                  @click="testConnection(connection)"
                >
                  <SignalIcon
                    class="mr-1.5 h-4 w-4"
                    :class="testingId === connection.id ? 'animate-pulse' : ''"
                  />
                  {{ t('connectors.test') }}
                </button>
                <button class="btn-secondary" @click="openEdit(connection)">
                  {{ t('connectors.edit') }}
                </button>
                <button
                  class="btn-secondary text-red-600 dark:text-red-400"
                  @click="remove(connection)"
                >
                  {{ t('connectors.delete') }}
                </button>
              </div>
            </div>

            <!-- Configuration non secrète : ce qui est réellement enregistré. -->
            <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
              <div
                v-for="(value, key) in connection.config"
                :key="key"
                class="flex items-baseline justify-between gap-3 text-sm"
              >
                <dt class="text-slate-500 dark:text-slate-400">{{ fieldLabel(connection.catalog_key, key) }}</dt>
                <dd class="truncate font-medium text-slate-800 dark:text-slate-200">{{ value }}</dd>
              </div>
            </dl>

            <!-- Secrets : masques uniquement, jamais la valeur. -->
            <div v-if="secretEntries(connection).length" class="mt-3 flex flex-wrap gap-2">
              <span
                v-for="[key, info] in secretEntries(connection)"
                :key="key"
                class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300"
              >
                {{ fieldLabel(connection.catalog_key, key) }} :
                {{ info.configured ? info.mask : t('connectors.notConfigured') }}
              </span>
            </div>

            <!-- Verdict du dernier test : le détail des contrôles exécutés. -->
            <div
              v-if="testResults[connection.id]"
              class="mt-4 rounded-lg border p-3 text-sm"
              :class="
                testResults[connection.id].status === 'validated'
                  ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                  : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200'
              "
            >
              <p class="font-medium">{{ testResults[connection.id].message }}</p>
              <ul v-if="testResults[connection.id].checks?.length" class="mt-2 space-y-1">
                <li
                  v-for="check in testResults[connection.id].checks"
                  :key="check.key"
                  class="flex items-start gap-2 text-xs"
                >
                  <span>{{ check.ok ? '✓' : '✕' }}</span>
                  <span>{{ check.detail }}</span>
                </li>
              </ul>
            </div>
          </article>
        </div>
      </section>

      <!-- ── Intégrations existantes (lecture seule) ───────────────────── -->
      <section class="space-y-4">
        <div>
          <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
            {{ t('connectors.existingIntegrations') }}
          </h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {{ t('connectors.existingIntegrationsHint') }}
          </p>
        </div>

        <div class="glass-card overflow-hidden">
          <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  {{ t('connectors.table.integration') }}
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  {{ t('connectors.table.state') }}
                </th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  {{ t('connectors.table.action') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr v-for="integration in integrations" :key="integration.key">
                <td class="px-4 py-3">
                  <p class="text-sm font-medium text-slate-900 dark:text-white">
                    {{ integration.label }}
                  </p>
                  <p class="text-xs text-slate-500 dark:text-slate-400">{{ integration.detail }}</p>
                </td>
                <td class="px-4 py-3">
                  <span
                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="
                      integration.configured
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300'
                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                    "
                  >
                    {{
                      integration.configured
                        ? t('connectors.configuredCount', { count: integration.count })
                        : t('connectors.notConfigured')
                    }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <router-link :to="integration.href" class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                    {{ t('connectors.openNativeScreen') }}
                  </router-link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <!-- ── Formulaire : GÉNÉRÉ depuis le schéma du catalogue ───────────── -->
    <div
      v-if="formOpen"
      class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="connector-form-title"
      @click.self="closeForm"
    >
      <div class="glass-card my-8 w-full max-w-2xl p-6">
        <div class="flex items-start justify-between gap-4">
          <h2 id="connector-form-title" class="text-lg font-semibold text-slate-900 dark:text-white">
            {{ editingId ? t('connectors.editConnection') : t('connectors.newConnection') }}
          </h2>
          <button class="text-slate-400 hover:text-slate-600" @click="closeForm">✕</button>
        </div>

        <div class="mt-5 space-y-4">
          <!-- Type de connecteur : verrouillé en édition (le schéma en dépend). -->
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ t('connectors.fields.type') }}
            </label>
            <select
              v-model="form.catalog_key"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
              :disabled="Boolean(editingId)"
              @change="onCatalogChange"
            >
              <option value="" disabled>{{ t('connectors.fields.typePlaceholder') }}</option>
              <option v-for="entry in catalog" :key="entry.key" :value="entry.key">
                {{ entry.label }}
              </option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ t('connectors.fields.label') }}
            </label>
            <input
              v-model="form.label"
              type="text"
              maxlength="120"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
              :placeholder="t('connectors.fields.labelPlaceholder')"
            />
          </div>

          <!-- Champs dynamiques : rien n'est codé en dur ici. -->
          <div v-for="field in activeFields" :key="field.key" class="space-y-1">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ field.label }}
              <span v-if="field.required" class="text-red-500">*</span>
            </label>

            <select
              v-if="field.type === 'select'"
              v-model="form.config[field.key]"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            >
              <option value="" disabled>{{ t('connectors.fields.selectPlaceholder') }}</option>
              <option v-for="option in field.options || []" :key="option.value" :value="option.value">
                {{ option.label || option.value }}
              </option>
            </select>

            <textarea
              v-else-if="field.type === 'textarea'"
              v-model="target(field).value"
              rows="3"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-xs dark:border-slate-600 dark:bg-slate-800 dark:text-white"
              :placeholder="field.placeholder || ''"
            />

            <input
              v-else
              v-model="target(field).value"
              :type="field.secret ? 'password' : inputType(field.type)"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
              :placeholder="placeholderFor(field)"
              autocomplete="off"
            />

            <p v-if="field.help" class="text-xs text-slate-500 dark:text-slate-400">{{ field.help }}</p>
            <p v-if="field.secret" class="text-xs text-slate-500 dark:text-slate-400">
              {{ t('connectors.fields.secretHint') }}
            </p>
          </div>

          <p v-if="formError" class="text-sm text-red-600 dark:text-red-400">{{ formError }}</p>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <button class="btn-secondary" @click="closeForm">{{ t('connectors.cancel') }}</button>
          <button class="btn-primary" :disabled="saving" @click="save">
            {{ saving ? t('connectors.saving') : t('connectors.save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { PlusIcon, SignalIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

/**
 * #9849 (BC-14 INTEGRATION) — onglet « Connecteurs », surface SUPERADMIN.
 *
 * Trois zones :
 *   1. les connexions de la PLATEFORME (banques qui reçoivent les paiements des
 *      tenants, PSP) — connectables dynamiquement, plus de config en dur ;
 *   2. le formulaire d'ajout, GÉNÉRÉ depuis `field_schema` du catalogue : aucun
 *      champ n'est codé ici, donc ajouter un type de connecteur ne demande
 *      aucune modification de cet écran ;
 *   3. l'index des intégrations DÉJÀ existantes, en lecture seule, avec un lien
 *      vers leur écran natif — c'est lui qui reste la source de vérité.
 *
 * Sécurité côté écran : les secrets sont write-only. L'API ne renvoie qu'un
 * masque, affiché en placeholder ; laisser un champ vide conserve la valeur en
 * place.
 */

const localeStore = useLocaleStore()
const toast = useToast()

const loading = ref(true)
const error = ref('')
const catalog = ref([])
const connections = ref([])
const integrations = ref([])
const activeCount = ref(0)

const formOpen = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formError = ref('')
const testingId = ref(null)
const testResults = reactive({})

const form = reactive({
  catalog_key: '',
  label: '',
  config: {},
  secrets: {},
})

function t(key, params) {
  const raw = translate(localeStore.current, key, key)

  // `translate()` ne gère pas l'interpolation : on remplace les `{jeton}`
  // nous-mêmes, sinon l'écran afficherait « {active} sur {total} ».
  if (!params) {
    return raw
  }

  return Object.entries(params).reduce(
    (text, [name, value]) => text.replaceAll(`{${name}}`, String(value)),
    raw
  )
}

const activeFields = computed(() => {
  const entry = catalog.value.find((item) => item.key === form.catalog_key)
  return entry?.fields || []
})

function inputType(type) {
  if (type === 'url') return 'url'
  if (type === 'email') return 'email'
  if (type === 'tel') return 'tel'
  if (type === 'number') return 'number'
  return 'text'
}

/**
 * Renvoie la tranche de formulaire (config ou secrets) où écrire la valeur.
 * `v-model` a besoin d'un objet, pas d'une expression conditionnelle.
 */
function target(field) {
  return field.secret
    ? { get value() { return form.secrets[field.key] ?? '' }, set value(v) { form.secrets[field.key] = v } }
    : { get value() { return form.config[field.key] ?? '' }, set value(v) { form.config[field.key] = v } }
}

function placeholderFor(field) {
  if (field.secret) {
    const existing = editingId.value ? currentSecrets.value[field.key] : null
    return existing?.configured ? existing.mask : t('connectors.fields.secretPlaceholder')
  }
  return field.placeholder || ''
}

const currentSecrets = computed(() => {
  const connection = connections.value.find((item) => item.id === editingId.value)
  return connection?.secrets || {}
})

function catalogLabel(key) {
  return catalog.value.find((item) => item.key === key)?.label || key
}

function fieldLabel(catalogKey, fieldKey) {
  const entry = catalog.value.find((item) => item.key === catalogKey)
  const field = entry?.fields?.find((item) => item.key === fieldKey)
  return field?.label || fieldKey
}

function secretEntries(connection) {
  return Object.entries(connection.secrets || {})
}

function statusBadgeClass(status) {
  if (status === 'active') {
    return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300'
  }
  if (status === 'error') {
    return 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'
  }
  if (status === 'revoked') {
    return 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
  }
  return 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300'
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [catalogResponse, connectionsResponse, overviewResponse] = await Promise.all([
      api.get('/platform/connectors/catalog'),
      api.get('/platform/connectors/connections'),
      api.get('/platform/connectors/overview'),
    ])

    catalog.value = catalogResponse.data?.data?.items || []
    connections.value = connectionsResponse.data?.data?.items || []
    integrations.value = overviewResponse.data?.data?.integrations || []
    activeCount.value = overviewResponse.data?.data?.active_count || 0
  } catch (err) {
    error.value = err?.response?.data?.localized_message || t('connectors.loadError')
  } finally {
    loading.value = false
  }
}

function resetForm() {
  form.catalog_key = ''
  form.label = ''
  form.config = {}
  form.secrets = {}
  formError.value = ''
}

function openCreate() {
  resetForm()
  editingId.value = null
  formOpen.value = true
}

function openEdit(connection) {
  resetForm()
  editingId.value = connection.id
  form.catalog_key = connection.catalog_key
  form.label = connection.label
  form.config = { ...(connection.config || {}) }
  // Secrets write-only : champs vides = « conserver la valeur en place ».
  form.secrets = Object.fromEntries(
    (catalog.value.find((item) => item.key === connection.catalog_key)?.fields || [])
      .filter((field) => field.secret)
      .map((field) => [field.key, ''])
  )
  formOpen.value = true
}

function closeForm() {
  formOpen.value = false
  editingId.value = null
  resetForm()
}

function onCatalogChange() {
  // Le schéma change avec le type : on repart d'un formulaire vierge pour ne
  // pas envoyer les champs d'un autre connecteur.
  form.config = {}
  form.secrets = {}
  formError.value = ''
}

async function save() {
  saving.value = true
  formError.value = ''

  try {
    // Write-only : seuls les secrets réellement saisis partent dans la requête.
    const secrets = Object.fromEntries(
      Object.entries(form.secrets).filter(([, value]) => (value || '').trim() !== '')
    )

    const payload = {
      label: form.label,
      config: form.config,
      ...(Object.keys(secrets).length ? { secrets } : {}),
    }

    if (editingId.value) {
      await api.patch(`/platform/connectors/connections/${editingId.value}`, payload)
    } else {
      await api.post('/platform/connectors/connections', { ...payload, catalog_key: form.catalog_key })
    }

    toast.success(t('connectors.saved'))
    closeForm()
    await load()
  } catch (err) {
    const data = err?.response?.data
    // Le détail par champ est ce qui rend l'erreur actionnable.
    formError.value = data?.violations?.length
      ? data.violations.join(' ')
      : data?.localized_message || t('connectors.saveError')
  } finally {
    saving.value = false
  }
}

async function testConnection(connection) {
  testingId.value = connection.id
  delete testResults[connection.id]

  try {
    const response = await api.post(`/platform/connectors/connections/${connection.id}/test`)
    testResults[connection.id] = response.data?.data?.check || {}
    await load()
  } catch (err) {
    testResults[connection.id] = {
      status: 'unreachable',
      message: err?.response?.data?.localized_message || t('connectors.testError'),
      checks: [],
    }
  } finally {
    testingId.value = null
  }
}

async function remove(connection) {
  if (!window.confirm(t('connectors.confirmDelete', { label: connection.label }))) {
    return
  }

  try {
    await api.delete(`/platform/connectors/connections/${connection.id}`)
    toast.success(t('connectors.deleted'))
    await load()
  } catch (err) {
    toast.error(err?.response?.data?.localized_message || t('connectors.deleteError'))
  }
}

onMounted(load)
</script>
