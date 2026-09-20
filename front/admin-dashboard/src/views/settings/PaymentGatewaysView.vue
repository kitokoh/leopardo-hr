<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('paymentGateways.title') }}
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('paymentGateways.subtitle') }}
      </p>
    </div>

    <div v-if="loading" class="glass-card p-6 text-sm text-slate-500 dark:text-slate-400">
      {{ t('paymentGateways.loading') }}
    </div>
    <div v-else-if="error" class="glass-card p-6 text-sm text-red-600 dark:text-red-400">
      {{ error }}
    </div>

    <div v-else class="grid grid-cols-1 gap-6 xl:grid-cols-2">
      <div v-for="gw in gateways" :key="gw.gateway" class="glass-card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <h2 class="text-lg font-semibold capitalize text-slate-900 dark:text-white">
              {{ gw.gateway }}
            </h2>
            <!-- État visuel : configuré via BDD / via env / non configuré -->
            <span
              class="px-2 py-0.5 rounded-full text-xs font-semibold"
              :class="sourceBadgeClass(gw.source)"
            >
              {{ t(`paymentGateways.source.${gw.source}`) }}
            </span>
          </div>
          <button
            class="btn-secondary"
            :disabled="testing === gw.gateway"
            @click="testConnection(gw.gateway)"
          >
            <SignalIcon class="h-4 w-4 mr-2" :class="testing === gw.gateway ? 'animate-pulse' : ''" />
            {{ t('paymentGateways.testConnection') }}
          </button>
        </div>

        <div class="mt-5 space-y-4">
          <!-- Toggle test / live -->
          <div class="flex items-center gap-4">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ t('paymentGateways.mode') }}
            </span>
            <div class="inline-flex rounded-lg border border-slate-200/60 p-0.5 dark:border-slate-700/60">
              <button
                v-for="mode in ['test', 'live']"
                :key="mode"
                class="rounded-md px-3 py-1 text-xs font-semibold transition"
                :class="
                  forms[gw.gateway].mode === mode
                    ? 'bg-indigo-600 text-white'
                    : 'text-slate-600 dark:text-slate-300'
                "
                @click="forms[gw.gateway].mode = mode"
              >
                {{ t(`paymentGateways.mode_${mode}`) }}
              </button>
            </div>
            <label class="ml-auto flex items-center gap-2">
              <input v-model="forms[gw.gateway].is_active" type="checkbox" class="h-4 w-4 rounded" />
              <span class="text-sm text-slate-700 dark:text-slate-300">{{ t('paymentGateways.isActive') }}</span>
            </label>
          </div>

          <!-- Secrets write-only : placeholder = masque de la valeur en place -->
          <label v-for="field in SECRET_FIELDS[gw.gateway]" :key="field" class="block">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ t(`paymentGateways.field.${gw.gateway}.${field}`) }}
            </span>
            <input
              v-model="forms[gw.gateway].secrets[field]"
              type="password"
              autocomplete="off"
              :placeholder="gw.secrets[field]?.mask || t('paymentGateways.secretEmpty')"
              class="input mt-1 w-full font-mono"
            />
            <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">
              {{ gw.secrets[field]?.configured ? t('paymentGateways.secretKeepHint') : t('paymentGateways.secretMissing') }}
            </span>
          </label>

          <!-- Config non secrète (price IDs Stripe par plan) -->
          <label v-for="field in CONFIG_FIELDS[gw.gateway]" :key="field" class="block">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ t(`paymentGateways.field.${gw.gateway}.${field}`) }}
            </span>
            <input
              v-model="forms[gw.gateway].config[field]"
              type="text"
              autocomplete="off"
              class="input mt-1 w-full font-mono"
            />
          </label>
        </div>

        <div class="mt-6 flex items-center justify-between gap-3">
          <p v-if="testResults[gw.gateway]" class="text-sm" :class="testResults[gw.gateway].ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
            {{ t(`paymentGateways.testStatus.${testResults[gw.gateway].status}`) }}
          </p>
          <span v-else></span>
          <button class="btn-primary" :disabled="saving === gw.gateway" @click="save(gw.gateway)">
            <CloudArrowUpIcon v-if="saving === gw.gateway" class="h-4 w-4 mr-2 animate-pulse" />
            {{ t('paymentGateways.save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { CloudArrowUpIcon, SignalIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

/**
 * #7726 (BC-21 BILLING) — « Passerelles de paiement » : la configuration PSP
 * (Stripe, Chargily) devient paramétrable depuis l'admin, plus par variables
 * d'environnement uniquement (constat D8 BC-21-BILLING-MATURITY).
 *
 * Sécurité côté écran :
 *  - les secrets sont WRITE-ONLY : l'API ne renvoie qu'un masque
 *    (`sk_live_••••1234`), affiché en placeholder — laisser un champ vide
 *    conserve la valeur en place ;
 *  - l'état de chaque passerelle est explicite : configurée via BDD, via env
 *    (fallback), ou non configurée ;
 *  - « Tester la connexion » vérifie les clés réellement résolues (ping
 *    Stripe /v1/account, Chargily /api/v2/balance) sans exposer de clé.
 */

const SECRET_FIELDS = {
  stripe: ['secret_key', 'webhook_secret'],
  chargily: ['api_key', 'webhook_secret'],
}

const CONFIG_FIELDS = {
  stripe: ['price_pilot', 'price_operations', 'price_enterprise'],
  chargily: [],
}

const localeStore = useLocaleStore()
const toast = useToast()

const gateways = ref([])
const loading = ref(true)
const error = ref('')
const saving = ref(null)
const testing = ref(null)
const testResults = reactive({})

const forms = reactive({
  stripe: { mode: 'test', is_active: true, config: {}, secrets: {} },
  chargily: { mode: 'test', is_active: true, config: {}, secrets: {} },
})

function t(key) {
  return translate(localeStore.current, key, key)
}

function sourceBadgeClass(source) {
  if (source === 'database') {
    return 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300'
  }
  if (source === 'env') {
    return 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300'
  }
  return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await api.get('/platform/billing/gateways')
    gateways.value = response.data?.data?.items || []
    for (const gw of gateways.value) {
      forms[gw.gateway].mode = gw.mode || 'test'
      forms[gw.gateway].is_active = gw.is_active !== false
      forms[gw.gateway].config = { ...(gw.config || {}) }
      // Les secrets ne redescendent jamais : champs vides = « conserver ».
      forms[gw.gateway].secrets = Object.fromEntries(
        (SECRET_FIELDS[gw.gateway] || []).map((field) => [field, ''])
      )
    }
  } catch (err) {
    error.value = err?.response?.data?.message || t('paymentGateways.loadError')
  } finally {
    loading.value = false
  }
}

async function save(gateway) {
  saving.value = gateway
  try {
    const form = forms[gateway]
    // Write-only : seuls les secrets saisis (non vides) partent dans le PUT.
    const secrets = Object.fromEntries(
      Object.entries(form.secrets).filter(([, value]) => (value || '').trim() !== '')
    )
    await api.put('/platform/billing/gateways', {
      gateway,
      mode: form.mode,
      is_active: Boolean(form.is_active),
      config: form.config,
      ...(Object.keys(secrets).length ? { secrets } : {}),
    })
    toast.success(t('paymentGateways.saved'))
    await load()
  } catch (err) {
    const validation = err?.response?.data?.errors
    toast.error(
      validation
        ? Object.values(validation).flat().join(' ')
        : err?.response?.data?.message || t('paymentGateways.saveError')
    )
  } finally {
    saving.value = null
  }
}

async function testConnection(gateway) {
  testing.value = gateway
  delete testResults[gateway]
  try {
    const response = await api.post(`/platform/billing/gateways/${gateway}/test`)
    testResults[gateway] = response.data?.data || { ok: true, status: 'OK' }
  } catch (err) {
    testResults[gateway] = err?.response?.data?.data || { ok: false, status: 'CONNECTION_FAILED' }
  } finally {
    testing.value = null
  }
}

onMounted(load)
</script>
