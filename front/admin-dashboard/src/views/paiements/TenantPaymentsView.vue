<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
          {{ t('tenantPayments.title') }}
        </h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          {{ t('tenantPayments.subtitle') }}
        </p>
      </div>
      <button class="btn-secondary" :disabled="loading" @click="reload">
        <ArrowPathIcon class="mr-2 h-4 w-4" :class="loading ? 'animate-spin' : ''" />
        {{ t('tenantPayments.refresh') }}
      </button>
    </div>

    <div v-if="error" class="glass-card p-4 text-sm text-red-600 dark:text-red-400">
      {{ error }}
    </div>

    <!-- ── Solde + marche ── -->
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
      <div class="glass-card p-6 xl:col-span-2">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
          {{ t('tenantPayments.balance.title') }}
        </h2>

        <p class="mt-4 text-3xl font-bold text-slate-900 dark:text-white">
          {{ balance.balance ?? 0 }}
          <span class="text-lg font-semibold text-slate-500 dark:text-slate-400">
            {{ balance.currency || '' }}
          </span>
        </p>

        <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt class="text-slate-500 dark:text-slate-400">
              {{ t('tenantPayments.balance.walletId') }}
            </dt>
            <dd class="font-mono text-slate-900 dark:text-white">{{ balance.wallet_id ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-slate-500 dark:text-slate-400">
              {{ t('tenantPayments.balance.source') }}
            </dt>
            <dd class="text-slate-900 dark:text-white">
              {{ balance.currency_source || '—' }}
            </dd>
          </div>
        </dl>
      </div>

      <div class="glass-card p-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
          {{ t('tenantPayments.market.title') }}
        </h2>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
          {{ t('tenantPayments.market.hint') }}
        </p>

        <dl class="mt-4 space-y-3 text-sm">
          <div>
            <dt class="text-slate-500 dark:text-slate-400">{{ t('tenantPayments.market.country') }}</dt>
            <dd class="text-slate-900 dark:text-white">{{ market.country || '—' }}</dd>
          </div>
          <div>
            <dt class="text-slate-500 dark:text-slate-400">{{ t('tenantPayments.market.currency') }}</dt>
            <dd class="text-slate-900 dark:text-white">{{ market.currency || '—' }}</dd>
          </div>
          <div>
            <dt class="text-slate-500 dark:text-slate-400">{{ t('tenantPayments.market.provider') }}</dt>
            <dd class="text-slate-900 dark:text-white">
              {{ market.provider_instance?.display_name || t('tenantPayments.market.none') }}
            </dd>
          </div>
        </dl>
      </div>
    </div>

    <!-- ── Demande de retrait ── -->
    <div class="glass-card p-6">
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
        {{ t('tenantPayments.cashOut.title') }}
      </h2>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('tenantPayments.cashOut.hint') }}
      </p>

      <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
        <input
          v-model.number="cashOutForm.amount"
          type="number"
          min="1"
          class="input w-full"
          :placeholder="t('tenantPayments.cashOut.amount')"
        />
        <input
          v-model="cashOutForm.currency"
          type="text"
          maxlength="3"
          class="input w-full font-mono uppercase"
          :placeholder="t('tenantPayments.cashOut.currency')"
        />
        <input
          v-model="cashOutForm.idempotency_key"
          type="text"
          class="input w-full font-mono"
          :placeholder="t('tenantPayments.cashOut.idempotencyKey')"
        />
      </div>

      <div class="mt-4 flex justify-end">
        <button class="btn-primary" :disabled="submitting" @click="submitCashOut">
          {{ submitting ? t('tenantPayments.cashOut.submitting') : t('tenantPayments.cashOut.submit') }}
        </button>
      </div>
    </div>

    <!-- ── Historique ── -->
    <div class="glass-card p-6">
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
        {{ t('tenantPayments.history.title') }}
      </h2>

      <p v-if="!history.length" class="mt-4 text-sm text-slate-500 dark:text-slate-400">
        {{ t('tenantPayments.history.empty') }}
      </p>

      <div v-else class="mt-4 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase text-slate-500 dark:text-slate-400">
              <th class="px-3 py-2">{{ t('tenantPayments.history.id') }}</th>
              <th class="px-3 py-2">{{ t('tenantPayments.history.type') }}</th>
              <th class="px-3 py-2">{{ t('tenantPayments.history.amount') }}</th>
              <th class="px-3 py-2">{{ t('tenantPayments.history.currency') }}</th>
              <th class="px-3 py-2">{{ t('tenantPayments.history.status') }}</th>
              <th class="px-3 py-2">{{ t('tenantPayments.history.createdAt') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in history"
              :key="row.id"
              class="border-t border-slate-200/60 dark:border-slate-700/60"
            >
              <td class="px-3 py-2 font-mono text-slate-900 dark:text-white">{{ row.id }}</td>
              <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ row.type }}</td>
              <td class="px-3 py-2 text-slate-900 dark:text-white">{{ row.amount }}</td>
              <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ row.currency }}</td>
              <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ row.status }}</td>
              <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ row.created_at }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Commissions ── -->
    <div class="glass-card p-6">
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
        {{ t('tenantPayments.fees.title') }}
      </h2>

      <p v-if="!fees.length" class="mt-4 text-sm text-slate-500 dark:text-slate-400">
        {{ t('tenantPayments.fees.empty') }}
      </p>

      <div v-else class="mt-4 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase text-slate-500 dark:text-slate-400">
              <th class="px-3 py-2">{{ t('tenantPayments.fees.transactionId') }}</th>
              <th class="px-3 py-2">{{ t('tenantPayments.fees.type') }}</th>
              <th class="px-3 py-2">{{ t('tenantPayments.fees.amount') }}</th>
              <th class="px-3 py-2">{{ t('tenantPayments.fees.fee') }}</th>
              <th class="px-3 py-2">{{ t('tenantPayments.fees.currency') }}</th>
              <th class="px-3 py-2">{{ t('tenantPayments.fees.createdAt') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in fees"
              :key="row.transaction_id"
              class="border-t border-slate-200/60 dark:border-slate-700/60"
            >
              <td class="px-3 py-2 font-mono text-slate-900 dark:text-white">
                {{ row.transaction_id }}
              </td>
              <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ row.type }}</td>
              <td class="px-3 py-2 text-slate-900 dark:text-white">{{ row.amount }}</td>
              <td class="px-3 py-2 text-slate-900 dark:text-white">{{ row.fee_amount }}</td>
              <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ row.currency }}</td>
              <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ row.created_at }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { ArrowPathIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

/**
 * BC-38 PAIEMENTS (#8739) — surface Tenant du socle de paiement.
 *
 * Le tenant voit son solde, son historique, ses commissions et demande un
 * retrait. Il ne choisit JAMAIS son fournisseur : le marche (pays + devise) est
 * resolu cote serveur, et l'ecran ne propose donc aucune selection d'instance —
 * c'est le contrat du backend, pas une simplification d'interface.
 *
 * L'isolation inter-tenant n'est pas assuree ici : elle l'est par le scope
 * global `company` du backend (fail-closed). L'ecran ne fait que consommer des
 * routes deja bornees au tenant authentifie.
 */

const localeStore = useLocaleStore()
const toast = useToast()

const loading = ref(false)
const error = ref('')
const submitting = ref(false)

const balance = ref({})
const market = ref({})
const history = ref([])
const fees = ref([])

const cashOutForm = reactive({
  amount: null,
  currency: '',
  idempotency_key: '',
})

function t(key) {
  return translate(localeStore.current, key, key)
}

async function loadBalance() {
  const response = await api.get('/paiements/wallet')
  balance.value = response.data?.data || {}
}

async function loadMarket() {
  const response = await api.get('/paiements/market')
  market.value = response.data?.data || {}
}

async function loadHistory() {
  const response = await api.get('/paiements/wallet/history')
  history.value = response.data?.data || []
}

async function loadFees() {
  const response = await api.get('/paiements/fees')
  fees.value = response.data?.data || []
}

async function reload() {
  loading.value = true
  error.value = ''
  try {
    await Promise.all([loadBalance(), loadMarket(), loadHistory(), loadFees()])
  } catch (e) {
    error.value = t('tenantPayments.loadError')
    console.warn('[tenant-payments] load failed', e)
  } finally {
    loading.value = false
  }
}

async function submitCashOut() {
  submitting.value = true
  try {
    const payload = {
      amount: Number(cashOutForm.amount) || 0,
      idempotency_key: cashOutForm.idempotency_key,
    }

    const currency = String(cashOutForm.currency || '').trim().toUpperCase()
    if (currency !== '') {
      payload.currency = currency
    }

    await api.post('/paiements/cash-out', payload)

    toast.success(t('tenantPayments.cashOut.submitted'))
    cashOutForm.amount = null
    cashOutForm.currency = ''
    cashOutForm.idempotency_key = ''
    await reload()
  } catch (e) {
    const code = e?.response?.data?.code
    toast.error(
      code === 'provider_not_resolvable'
        ? t('tenantPayments.cashOut.providerNotResolvable')
        : t('tenantPayments.cashOut.submitError'),
    )
    console.warn('[tenant-payments] cash-out failed', e)
  } finally {
    submitting.value = false
  }
}

onMounted(reload)
</script>
