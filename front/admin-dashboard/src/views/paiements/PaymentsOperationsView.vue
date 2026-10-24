<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
          {{ t('paymentsOperations.title') }}
        </h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          {{ t('paymentsOperations.subtitle') }}
        </p>
      </div>
      <button class="btn-secondary" :disabled="loading" @click="reload">
        <ArrowPathIcon class="mr-2 h-4 w-4" :class="loading ? 'animate-spin' : ''" />
        {{ t('paymentsOperations.refresh') }}
      </button>
    </div>

    <div v-if="error" class="glass-card p-4 text-sm text-red-600 dark:text-red-400">
      {{ error }}
    </div>

    <div class="flex flex-wrap gap-2">
      <button
        v-for="tab in TABS"
        :key="tab"
        class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
        :class="
          activeTab === tab
            ? 'bg-indigo-600 text-white'
            : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
        "
        @click="activeTab = tab"
      >
        {{ t(`paymentsOperations.tabs.${tab}`) }}
      </button>
    </div>

    <!-- ── Types de fournisseur (LECTURE SEULE : la source est le code) ── -->
    <section v-if="activeTab === 'types'" class="glass-card p-6">
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
        {{ t('paymentsOperations.types.title') }}
      </h2>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('paymentsOperations.types.hint') }}
      </p>

      <p v-if="!types.length" class="mt-4 text-sm text-slate-500 dark:text-slate-400">
        {{ t('paymentsOperations.types.empty') }}
      </p>

      <div v-else class="mt-4 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase text-slate-500 dark:text-slate-400">
              <th class="px-3 py-2">{{ t('paymentsOperations.types.code') }}</th>
              <th class="px-3 py-2">{{ t('paymentsOperations.types.label') }}</th>
              <th class="px-3 py-2">{{ t('paymentsOperations.types.countries') }}</th>
              <th class="px-3 py-2">{{ t('paymentsOperations.types.currencies') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="type in types"
              :key="type.code || type.type_code"
              class="border-t border-slate-200/60 dark:border-slate-700/60"
            >
              <td class="px-3 py-2 font-mono text-slate-900 dark:text-white">
                {{ type.code || type.type_code }}
              </td>
              <td class="px-3 py-2 text-slate-700 dark:text-slate-300">
                {{ type.label || type.name || type.display_name }}
              </td>
              <td class="px-3 py-2 text-slate-500 dark:text-slate-400">
                {{ listOf(type.countries) }}
              </td>
              <td class="px-3 py-2 text-slate-500 dark:text-slate-400">
                {{ listOf(type.currencies) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ── Instances (CRUD : c'est la CONFIGURATION, pas le code) ── -->
    <section v-if="activeTab === 'instances'" class="space-y-6">
      <div class="glass-card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
              {{ t('paymentsOperations.instances.title') }}
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
              {{ t('paymentsOperations.instances.hint') }}
            </p>
          </div>
          <button class="btn-primary" @click="startCreate">
            <PlusIcon class="mr-2 h-4 w-4" />
            {{ t('paymentsOperations.instances.add') }}
          </button>
        </div>

        <p v-if="!instances.length" class="mt-4 text-sm text-slate-500 dark:text-slate-400">
          {{ t('paymentsOperations.instances.empty') }}
        </p>

        <div v-else class="mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-xs uppercase text-slate-500 dark:text-slate-400">
                <th class="px-3 py-2">{{ t('paymentsOperations.instances.displayName') }}</th>
                <th class="px-3 py-2">{{ t('paymentsOperations.instances.type') }}</th>
                <th class="px-3 py-2">{{ t('paymentsOperations.instances.company') }}</th>
                <th class="px-3 py-2">{{ t('paymentsOperations.instances.countries') }}</th>
                <th class="px-3 py-2">{{ t('paymentsOperations.instances.currencies') }}</th>
                <th class="px-3 py-2">{{ t('paymentsOperations.instances.status') }}</th>
                <th class="px-3 py-2">{{ t('paymentsOperations.instances.priority') }}</th>
                <th class="px-3 py-2">{{ t('paymentsOperations.instances.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="instance in instances"
                :key="instance.id"
                class="border-t border-slate-200/60 dark:border-slate-700/60"
              >
                <td class="px-3 py-2 text-slate-900 dark:text-white">{{ instance.display_name }}</td>
                <td class="px-3 py-2 font-mono text-slate-700 dark:text-slate-300">
                  {{ instance.type_code }}
                </td>
                <td class="px-3 py-2 text-slate-500 dark:text-slate-400">
                  {{ instance.company_id || t('paymentsOperations.instances.global') }}
                </td>
                <td class="px-3 py-2 text-slate-500 dark:text-slate-400">
                  {{ listOf(instance.countries) }}
                </td>
                <td class="px-3 py-2 text-slate-500 dark:text-slate-400">
                  {{ listOf(instance.currencies) }}
                </td>
                <td class="px-3 py-2">
                  <span
                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="statusBadgeClass(instance.status)"
                  >
                    {{ t(`paymentsOperations.instances.status_${instance.status}`) }}
                  </span>
                </td>
                <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ instance.priority }}</td>
                <td class="px-3 py-2">
                  <div class="flex flex-wrap items-center gap-2">
                    <button class="btn-secondary" @click="startEdit(instance)">
                      <PencilSquareIcon class="mr-1 h-4 w-4" />
                      {{ t('paymentsOperations.instances.edit') }}
                    </button>
                    <button
                      class="btn-secondary"
                      :disabled="busyId === instance.id"
                      @click="toggleStatus(instance)"
                    >
                      <CheckCircleIcon v-if="instance.status !== 'active'" class="mr-1 h-4 w-4" />
                      <XCircleIcon v-else class="mr-1 h-4 w-4" />
                      {{
                        instance.status === 'active'
                          ? t('paymentsOperations.instances.deactivate')
                          : t('paymentsOperations.instances.activate')
                      }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Formulaire instance -->
      <div v-if="formOpen" class="glass-card p-6">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">
          {{
            isEditing
              ? t('paymentsOperations.instances.editTitle')
              : t('paymentsOperations.instances.createTitle')
          }}
        </h3>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <label class="block">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ t('paymentsOperations.instances.displayName') }}
            </span>
            <input v-model="instanceForm.display_name" type="text" class="input mt-1 w-full" />
          </label>

          <label class="block">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ t('paymentsOperations.instances.type') }}
            </span>
            <select
              v-model="instanceForm.type_code"
              class="input mt-1 w-full"
              :disabled="isEditing"
            >
              <option value="">{{ t('paymentsOperations.instances.typePlaceholder') }}</option>
              <option v-for="type in types" :key="type.code || type.type_code" :value="type.code || type.type_code">
                {{ type.label || type.name || type.code || type.type_code }}
              </option>
            </select>
          </label>

          <label class="block">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ t('paymentsOperations.instances.company') }}
            </span>
            <input
              v-model="instanceForm.company_id"
              type="text"
              class="input mt-1 w-full font-mono"
              :placeholder="t('paymentsOperations.instances.globalHint')"
            />
          </label>

          <label class="block">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ t('paymentsOperations.instances.status') }}
            </span>
            <select v-model="instanceForm.status" class="input mt-1 w-full">
              <option v-for="status in STATUSES" :key="status" :value="status">
                {{ t(`paymentsOperations.instances.status_${status}`) }}
              </option>
            </select>
          </label>

          <label class="block">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ t('paymentsOperations.instances.countries') }}
            </span>
            <input
              v-model="instanceForm.countries"
              type="text"
              class="input mt-1 w-full font-mono"
              :placeholder="t('paymentsOperations.instances.listHint')"
            />
          </label>

          <label class="block">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ t('paymentsOperations.instances.currencies') }}
            </span>
            <input
              v-model="instanceForm.currencies"
              type="text"
              class="input mt-1 w-full font-mono"
              :placeholder="t('paymentsOperations.instances.listHint')"
            />
          </label>

          <label class="block">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ t('paymentsOperations.instances.priority') }}
            </span>
            <input v-model.number="instanceForm.priority" type="number" min="0" class="input mt-1 w-full" />
          </label>
        </div>

        <!-- Identifiants : write-only, jamais relus en clair -->
        <div class="mt-6">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                {{ t('paymentsOperations.instances.credentials') }}
              </span>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ t('paymentsOperations.instances.credentialsHint') }}
              </p>
            </div>
            <button class="btn-secondary" @click="addCredentialRow">
              <KeyIcon class="mr-1 h-4 w-4" />
              {{ t('paymentsOperations.instances.addCredential') }}
            </button>
          </div>

          <div
            v-for="(row, index) in instanceForm.credentials"
            :key="index"
            class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2"
          >
            <input
              v-model="row.key"
              type="text"
              class="input w-full font-mono"
              :placeholder="t('paymentsOperations.instances.credentialKey')"
            />
            <input
              v-model="row.value"
              type="password"
              autocomplete="off"
              class="input w-full font-mono"
              :placeholder="maskedPlaceholder(row.key)"
            />
          </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
          <button class="btn-secondary" @click="closeForm">
            {{ t('paymentsOperations.instances.cancel') }}
          </button>
          <button class="btn-primary" :disabled="saving" @click="saveInstance">
            {{ saving ? t('paymentsOperations.instances.saving') : t('paymentsOperations.instances.save') }}
          </button>
        </div>
      </div>
    </section>

    <!-- ── Journal des transactions ── -->
    <section v-if="activeTab === 'journal'" class="glass-card p-6">
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
        {{ t('paymentsOperations.journal.title') }}
      </h2>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('paymentsOperations.journal.hint') }}
      </p>

      <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
        <input
          v-model="journalFilters.company_id"
          type="text"
          class="input w-full font-mono"
          :placeholder="t('paymentsOperations.journal.company')"
        />
        <input
          v-model="journalFilters.provider_instance_id"
          type="text"
          class="input w-full font-mono"
          :placeholder="t('paymentsOperations.journal.instance')"
        />
        <input
          v-model="journalFilters.status"
          type="text"
          class="input w-full font-mono"
          :placeholder="t('paymentsOperations.journal.status')"
        />
        <div class="flex gap-2">
          <button class="btn-primary" @click="loadTransactions">
            {{ t('paymentsOperations.journal.filter') }}
          </button>
          <button class="btn-secondary" @click="resetJournalFilters">
            {{ t('paymentsOperations.journal.reset') }}
          </button>
        </div>
      </div>

      <p v-if="!transactions.length" class="mt-4 text-sm text-slate-500 dark:text-slate-400">
        {{ t('paymentsOperations.journal.empty') }}
      </p>

      <div v-else class="mt-4 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase text-slate-500 dark:text-slate-400">
              <th class="px-3 py-2">{{ t('paymentsOperations.journal.id') }}</th>
              <th class="px-3 py-2">{{ t('paymentsOperations.journal.company') }}</th>
              <th class="px-3 py-2">{{ t('paymentsOperations.journal.instance') }}</th>
              <th class="px-3 py-2">{{ t('paymentsOperations.journal.type') }}</th>
              <th class="px-3 py-2">{{ t('paymentsOperations.journal.status') }}</th>
              <th class="px-3 py-2">{{ t('paymentsOperations.journal.amount') }}</th>
              <th class="px-3 py-2">{{ t('paymentsOperations.journal.fee') }}</th>
              <th class="px-3 py-2">{{ t('paymentsOperations.journal.createdAt') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in transactions"
              :key="row.id"
              class="border-t border-slate-200/60 dark:border-slate-700/60"
            >
              <td class="px-3 py-2 font-mono text-slate-900 dark:text-white">{{ row.id }}</td>
              <td class="px-3 py-2 font-mono text-slate-500 dark:text-slate-400">
                {{ row.company_id }}
              </td>
              <td class="px-3 py-2 font-mono text-slate-500 dark:text-slate-400">
                {{ row.provider_instance_id }}
              </td>
              <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ row.type }}</td>
              <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ row.status }}</td>
              <td class="px-3 py-2 text-slate-900 dark:text-white">
                {{ row.amount }} {{ row.currency }}
              </td>
              <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ row.fee_amount }}</td>
              <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ row.created_at }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ── Réconciliation ── -->
    <section v-if="activeTab === 'reconciliation'" class="space-y-6">
      <div class="glass-card p-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
          {{ t('paymentsOperations.reconciliation.title') }}
        </h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          {{ t('paymentsOperations.reconciliation.hint') }}
        </p>

        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
          <input
            v-model="reconciliationForm.company_id"
            type="text"
            class="input w-full font-mono"
            :placeholder="t('paymentsOperations.reconciliation.company')"
          />
          <input
            v-model="reconciliationForm.provider_instance_id"
            type="text"
            class="input w-full font-mono"
            :placeholder="t('paymentsOperations.reconciliation.instance')"
          />
          <input
            v-model="reconciliationForm.currency"
            type="text"
            maxlength="3"
            class="input w-full font-mono uppercase"
            :placeholder="t('paymentsOperations.reconciliation.currency')"
          />
          <input
            v-model.number="reconciliationForm.declared_balance"
            type="number"
            class="input w-full"
            :placeholder="t('paymentsOperations.reconciliation.declaredBalance')"
          />
        </div>

        <div class="mt-4 flex justify-end">
          <button class="btn-primary" :disabled="running" @click="runReconciliation">
            {{ running ? t('paymentsOperations.reconciliation.running') : t('paymentsOperations.reconciliation.run') }}
          </button>
        </div>
      </div>

      <div class="glass-card p-6">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">
          {{ t('paymentsOperations.reconciliation.history') }}
        </h3>

        <p v-if="!reconciliations.length" class="mt-4 text-sm text-slate-500 dark:text-slate-400">
          {{ t('paymentsOperations.reconciliation.empty') }}
        </p>

        <div v-else class="mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-xs uppercase text-slate-500 dark:text-slate-400">
                <th class="px-3 py-2">{{ t('paymentsOperations.reconciliation.id') }}</th>
                <th class="px-3 py-2">{{ t('paymentsOperations.reconciliation.company') }}</th>
                <th class="px-3 py-2">{{ t('paymentsOperations.reconciliation.currency') }}</th>
                <th class="px-3 py-2">{{ t('paymentsOperations.reconciliation.declared') }}</th>
                <th class="px-3 py-2">{{ t('paymentsOperations.reconciliation.computed') }}</th>
                <th class="px-3 py-2">{{ t('paymentsOperations.reconciliation.difference') }}</th>
                <th class="px-3 py-2">{{ t('paymentsOperations.reconciliation.status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in reconciliations"
                :key="row.id"
                class="border-t border-slate-200/60 dark:border-slate-700/60"
              >
                <td class="px-3 py-2 font-mono text-slate-900 dark:text-white">{{ row.id }}</td>
                <td class="px-3 py-2 font-mono text-slate-500 dark:text-slate-400">
                  {{ row.company_id }}
                </td>
                <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ row.currency }}</td>
                <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ row.declared_balance }}</td>
                <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ row.computed_balance }}</td>
                <td class="px-3 py-2 text-slate-900 dark:text-white">{{ row.difference }}</td>
                <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ row.status }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
import {
  ArrowPathIcon,
  CheckCircleIcon,
  KeyIcon,
  PencilSquareIcon,
  PlusIcon,
  XCircleIcon,
} from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

/**
 * BC-38 PAIEMENTS (#8739) — surface Superadmin du socle de paiement.
 *
 * Le backend exposait deja les routes (`/platform/payments/*`) mais AUCUN ecran
 * ne les consommait : le BC etait inexploitable par un administrateur non
 * technique, et l'objectif « ajouter un moyen de paiement = zero ligne de code »
 * n'etait pas atteignable en pratique.
 *
 * Deux natures d'objets, deux regimes — c'est la separation du backend, et
 * l'ecran la respecte :
 *   - les TYPES viennent du code : lecture seule, aucun bouton d'ecriture ;
 *   - les INSTANCES sont de la configuration : CRUD complet + bascule de statut,
 *     ce qui rend le critere d'acceptation du BC operable par un humain.
 *
 * Secrets : l'API ne renvoie qu'un masque (`••••`). Les champs d'identifiants
 * sont donc WRITE-ONLY — laisser vide conserve la valeur en place, et rien de
 * ce qui est affiche n'est jamais renvoye au serveur.
 */

const TABS = ['types', 'instances', 'journal', 'reconciliation']
const STATUSES = ['active', 'inactive', 'sandbox']

const localeStore = useLocaleStore()
const toast = useToast()

const activeTab = ref('types')
const loading = ref(false)
const error = ref('')

const types = ref([])
const instances = ref([])
const transactions = ref([])
const reconciliations = ref([])

const journalFilters = reactive({
  company_id: '',
  provider_instance_id: '',
  status: '',
})

const reconciliationForm = reactive({
  company_id: '',
  provider_instance_id: '',
  currency: '',
  declared_balance: 0,
})

const formOpen = ref(false)
const editingId = ref(null)
const saving = ref(false)
const running = ref(false)
const busyId = ref(null)

const instanceForm = reactive({
  type_code: '',
  display_name: '',
  company_id: '',
  countries: '',
  currencies: '',
  status: 'inactive',
  priority: 100,
  credentials: [],
})

const isEditing = computed(() => editingId.value !== null)

function t(key) {
  return translate(localeStore.current, key, key)
}

function listOf(value) {
  if (!Array.isArray(value) || value.length === 0) {
    return t('paymentsOperations.instances.any')
  }
  return value.join(', ')
}

function statusBadgeClass(status) {
  if (status === 'active') {
    return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300'
  }
  if (status === 'sandbox') {
    return 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300'
  }
  return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
}

/** Masque affiche en placeholder pour une cle deja configuree. */
function maskedPlaceholder(key) {
  const existing = instances.value.find((i) => i.id === editingId.value)
  const config = existing?.config || {}
  if (key && Object.prototype.hasOwnProperty.call(config, key)) {
    return config[key]
  }
  return t('paymentsOperations.instances.credentialValue')
}

function addCredentialRow() {
  instanceForm.credentials.push({ key: '', value: '' })
}

function resetForm() {
  instanceForm.type_code = ''
  instanceForm.display_name = ''
  instanceForm.company_id = ''
  instanceForm.countries = ''
  instanceForm.currencies = ''
  instanceForm.status = 'inactive'
  instanceForm.priority = 100
  instanceForm.credentials = []
}

function startCreate() {
  resetForm()
  editingId.value = null
  formOpen.value = true
}

function startEdit(instance) {
  resetForm()
  editingId.value = instance.id
  instanceForm.type_code = instance.type_code
  instanceForm.display_name = instance.display_name
  instanceForm.company_id = instance.company_id || ''
  instanceForm.countries = (instance.countries || []).join(', ')
  instanceForm.currencies = (instance.currencies || []).join(', ')
  instanceForm.status = instance.status
  instanceForm.priority = instance.priority
  formOpen.value = true
}

function closeForm() {
  formOpen.value = false
  editingId.value = null
  resetForm()
}

function splitList(value) {
  return String(value || '')
    .split(',')
    .map((item) => item.trim())
    .filter((item) => item !== '')
}

/** Ne renvoie que les identifiants REELLEMENT saisis (valeur non vide). */
function credentialsPayload() {
  const config = {}
  for (const row of instanceForm.credentials) {
    const key = String(row.key || '').trim()
    const value = String(row.value || '')
    if (key !== '' && value !== '') {
      config[key] = value
    }
  }
  return config
}

async function loadTypes() {
  const response = await api.get('/platform/payments/provider-types')
  types.value = response.data?.data || []
}

async function loadInstances() {
  const response = await api.get('/platform/payments/provider-instances')
  instances.value = response.data?.data || []
}

async function loadTransactions() {
  const params = {}
  if (journalFilters.company_id) params.company_id = journalFilters.company_id
  if (journalFilters.provider_instance_id) {
    params.provider_instance_id = journalFilters.provider_instance_id
  }
  if (journalFilters.status) params.status = journalFilters.status

  const response = await api.get('/platform/payments/transactions', { params })
  transactions.value = response.data?.data || []
}

async function loadReconciliations() {
  const response = await api.get('/platform/payments/reconciliations')
  reconciliations.value = response.data?.data || []
}

function resetJournalFilters() {
  journalFilters.company_id = ''
  journalFilters.provider_instance_id = ''
  journalFilters.status = ''
  return loadTransactions()
}

async function reload() {
  loading.value = true
  error.value = ''
  try {
    await Promise.all([loadTypes(), loadInstances(), loadTransactions(), loadReconciliations()])
  } catch (e) {
    error.value = t('paymentsOperations.loadError')
    console.warn('[payments-operations] load failed', e)
  } finally {
    loading.value = false
  }
}

async function saveInstance() {
  saving.value = true
  try {
    const payload = {
      display_name: instanceForm.display_name,
      countries: splitList(instanceForm.countries),
      currencies: splitList(instanceForm.currencies),
      status: instanceForm.status,
      priority: Number(instanceForm.priority) || 0,
    }

    const config = credentialsPayload()
    if (Object.keys(config).length > 0) {
      payload.config = config
    }

    if (isEditing.value) {
      await api.patch(`/platform/payments/provider-instances/${editingId.value}`, payload)
    } else {
      await api.post('/platform/payments/provider-instances', {
        ...payload,
        type_code: instanceForm.type_code,
        company_id: instanceForm.company_id || null,
      })
    }

    toast.success(t('paymentsOperations.instances.saved'))
    closeForm()
    await loadInstances()
  } catch (e) {
    toast.error(t('paymentsOperations.instances.saveError'))
    console.warn('[payments-operations] save failed', e)
  } finally {
    saving.value = false
  }
}

async function toggleStatus(instance) {
  busyId.value = instance.id
  try {
    const next = instance.status === 'active' ? 'inactive' : 'active'
    await api.patch(`/platform/payments/provider-instances/${instance.id}/status`, { status: next })
    await loadInstances()
  } catch (e) {
    toast.error(t('paymentsOperations.instances.saveError'))
    console.warn('[payments-operations] status failed', e)
  } finally {
    busyId.value = null
  }
}

async function runReconciliation() {
  running.value = true
  try {
    await api.post('/platform/payments/reconciliations', {
      company_id: reconciliationForm.company_id,
      provider_instance_id: Number(reconciliationForm.provider_instance_id),
      currency: String(reconciliationForm.currency || '').toUpperCase(),
      declared_balance: Number(reconciliationForm.declared_balance) || 0,
    })
    toast.success(t('paymentsOperations.reconciliation.ran'))
    await loadReconciliations()
  } catch (e) {
    toast.error(t('paymentsOperations.reconciliation.runError'))
    console.warn('[payments-operations] reconciliation failed', e)
  } finally {
    running.value = false
  }
}

onMounted(reload)
</script>
