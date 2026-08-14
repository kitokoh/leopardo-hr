<template>
  <div class="space-y-8 animate-fade-in max-w-6xl">
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">Taux légaux — validation</h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        Workflow de validation des modifications de barèmes fiscaux et cotisations sociales
        (issue #1813). Les RH/comptables créent et soumettent ; seul le platform admin
        approuve ou rejette. Chaque transition est tracée dans un audit trail immuable.
      </p>
    </div>

    <!-- Section En attente de validation -->
    <div class="glass-card p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          ⏳ En attente de validation
          <span v-if="pendingCount > 0" class="ml-2 px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">
            {{ pendingCount }}
          </span>
        </h2>
        <button class="btn-secondary py-1 px-3 text-sm" :disabled="loading" @click="loadPending">
          Actualiser
        </button>
      </div>

      <p v-if="loading" class="py-6 text-center text-slate-400">Chargement…</p>

      <template v-else-if="pendingSlabs.length + pendingContributions.length > 0">
        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">Barèmes fiscaux</h3>
        <div class="overflow-x-auto mb-6">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-slate-500 dark:text-slate-400">
                <th class="py-2 pr-4 font-semibold">Entreprise</th>
                <th class="py-2 pr-4 font-semibold">Pays</th>
                <th class="py-2 pr-4 font-semibold">Nom / Tranche</th>
                <th class="py-2 pr-4 font-semibold">Taux</th>
                <th class="py-2 pr-4 font-semibold">Effectif le</th>
                <th class="py-2 font-semibold text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in pendingSlabs" :key="'slab-' + s.id" class="border-b border-slate-100 dark:border-slate-800">
                <td class="py-2.5 pr-4 font-medium text-slate-800 dark:text-slate-200">{{ companyName(s.company_id) }}</td>
                <td class="py-2.5 pr-4">{{ s.country_code }}</td>
                <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ s.name }} ({{ s.min_amount }} → {{ s.max_amount ?? '∞' }})</td>
                <td class="py-2.5 pr-4 font-semibold">{{ s.rate }} %</td>
                <td class="py-2.5 pr-4">{{ s.effective_from }}</td>
                <td class="py-2.5 text-right whitespace-nowrap">
                  <button class="btn-primary py-1 px-3 mr-2" :disabled="acting" @click="approve('slab', s)">Approuver</button>
                  <button class="btn-danger py-1 px-3" :disabled="acting" @click="openReject('slab', s)">Rejeter</button>
                </td>
              </tr>
              <tr v-if="pendingSlabs.length === 0">
                <td colspan="6" class="py-4 text-center text-slate-400">Aucun barème en attente.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">Cotisations sociales</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-slate-500 dark:text-slate-400">
                <th class="py-2 pr-4 font-semibold">Entreprise</th>
                <th class="py-2 pr-4 font-semibold">Pays</th>
                <th class="py-2 pr-4 font-semibold">Nom / Code</th>
                <th class="py-2 pr-4 font-semibold">Type</th>
                <th class="py-2 pr-4 font-semibold">Taux</th>
                <th class="py-2 pr-4 font-semibold">Plafond</th>
                <th class="py-2 font-semibold text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in pendingContributions" :key="'contrib-' + c.id" class="border-b border-slate-100 dark:border-slate-800">
                <td class="py-2.5 pr-4 font-medium text-slate-800 dark:text-slate-200">{{ companyName(c.company_id) }}</td>
                <td class="py-2.5 pr-4">{{ c.country_code }}</td>
                <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ c.name }} ({{ c.code }})</td>
                <td class="py-2.5 pr-4">{{ c.type }}</td>
                <td class="py-2.5 pr-4 font-semibold">{{ c.rate }} %</td>
                <td class="py-2.5 pr-4">{{ c.cap ?? '—' }}</td>
                <td class="py-2.5 text-right whitespace-nowrap">
                  <button class="btn-primary py-1 px-3 mr-2" :disabled="acting" @click="approve('contribution', c)">Approuver</button>
                  <button class="btn-danger py-1 px-3" :disabled="acting" @click="openReject('contribution', c)">Rejeter</button>
                </td>
              </tr>
              <tr v-if="pendingContributions.length === 0">
                <td colspan="7" class="py-4 text-center text-slate-400">Aucune cotisation en attente.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
      <p v-else class="py-6 text-center text-emerald-500 font-medium">
        ✅ Rien en attente — toutes les modifications ont été traitées.
      </p>
    </div>

    <!-- Barèmes (toutes lignes) -->
    <div class="glass-card p-6">
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-3">Barèmes fiscaux — toutes les lignes</h2>
        </div>
        <div class="ml-auto flex flex-wrap items-end gap-3">
          <div>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="tx-status">Statut</label>
            <select id="tx-status" v-model="slabFilter.status" class="form-input min-w-36" @change="loadSlabs">
              <option value="">Tous</option>
              <option value="active">🟢 Active</option>
              <option value="pending_validation">🟡 En attente</option>
              <option value="draft">⚪ Brouillon</option>
              <option value="superseded">🔵 Remplacée</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="tx-country">Pays</label>
            <select id="tx-country" v-model="slabFilter.country_code" class="form-input min-w-32" @change="loadSlabs">
              <option value="">Tous</option>
              <option v-for="cc in supportedCountries" :key="cc.code" :value="cc.code">{{ cc.label }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-4 font-semibold">Entreprise</th>
              <th class="py-2 pr-4 font-semibold">Pays</th>
              <th class="py-2 pr-4 font-semibold">Nom</th>
              <th class="py-2 pr-4 font-semibold">Tranche</th>
              <th class="py-2 pr-4 font-semibold">Taux</th>
              <th class="py-2 pr-4 font-semibold">Effectif</th>
              <th class="py-2 font-semibold">Statut</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in slabs" :key="'all-' + s.id" class="border-b border-slate-100 dark:border-slate-800">
              <td class="py-2.5 pr-4 font-medium text-slate-800 dark:text-slate-200">{{ companyName(s.company_id) }}</td>
              <td class="py-2.5 pr-4">{{ s.country_code }}</td>
              <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ s.name }}</td>
              <td class="py-2.5 pr-4">{{ s.min_amount }} → {{ s.max_amount ?? '∞' }}</td>
              <td class="py-2.5 pr-4 font-semibold">{{ s.rate }} %</td>
              <td class="py-2.5 pr-4">{{ s.effective_from }}<span v-if="s.effective_to"> → {{ s.effective_to }}</span></td>
              <td class="py-2.5"><span :class="statusBadge(s.status)">{{ statusLabel(s.status) }}</span></td>
            </tr>
            <tr v-if="slabs.length === 0">
              <td colspan="7" class="py-8 text-center text-slate-400">Aucun barème.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Historique immuable -->
    <div class="glass-card p-6">
      <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4">📜 Historique des modifications (audit trail immuable)</h2>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-4 font-semibold">Date</th>
              <th class="py-2 pr-4 font-semibold">Table</th>
              <th class="py-2 pr-4 font-semibold">Enregistrement</th>
              <th class="py-2 pr-4 font-semibold">Action</th>
              <th class="py-2 pr-4 font-semibold">Acteur</th>
              <th class="py-2 font-semibold">Motif</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="h in history" :key="'log-' + h.id" class="border-b border-slate-100 dark:border-slate-800">
              <td class="py-2.5 pr-4 whitespace-nowrap text-slate-600 dark:text-slate-400">{{ formatDate(h.created_at) }}</td>
              <td class="py-2.5 pr-4">{{ h.table_name }}</td>
              <td class="py-2.5 pr-4">#{{ h.record_id }}</td>
              <td class="py-2.5 pr-4"><span :class="historyBadge(h.action)">{{ historyLabel(h.action) }}</span></td>
              <td class="py-2.5 pr-4">{{ h.actor_role }} #{{ h.actor_id }}</td>
              <td class="py-2.5 text-slate-600 dark:text-slate-400">{{ h.reason || '—' }}</td>
            </tr>
            <tr v-if="history.length === 0">
              <td colspan="6" class="py-8 text-center text-slate-400">Aucune entrée d'audit.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal rejet -->
    <div v-if="rejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="rejectModal = false">
      <div class="glass-card w-full max-w-md p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4">Rejeter la modification</h2>
        <form class="space-y-4" @submit.prevent="submitReject">
          <div>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="reject-reason">Motif (obligatoire)</label>
            <textarea id="reject-reason" v-model="rejectReason" rows="3" class="form-input" minlength="5" required placeholder="Ex. : taux incohérent avec le barème officiel…"></textarea>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="btn-secondary" @click="rejectModal = false">Annuler</button>
            <button type="submit" class="btn-danger" :disabled="acting">Rejeter</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import api from '@/services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

const supportedCountries = [
  { code: 'DZ', label: 'Algérie' },
  { code: 'MA', label: 'Maroc' },
  { code: 'TN', label: 'Tunisie' },
  { code: 'SN', label: 'Sénégal' },
  { code: 'CM', label: 'Cameroun' },
  { code: 'CI', label: "Côte d'Ivoire" },
  { code: 'BF', label: 'Burkina Faso' },
  { code: 'ML', label: 'Mali' },
]

const loading = ref(false)
const acting = ref(false)
const pendingSlabs = ref([])
const pendingContributions = ref([])
const slabs = ref([])
const history = ref([])
const slabFilter = reactive({ status: '', country_code: '' })
const rejectModal = ref(false)
const rejectTarget = ref(null)
const rejectReason = ref('')

const pendingCount = computed(() => pendingSlabs.value.length + pendingContributions.value.length)

const companyCache = new Map()

function companyName(id) {
  if (!id) return '—'
  if (companyCache.has(id)) return companyCache.get(id)
  return id
}

async function loadCompanies() {
  try {
    const { data } = await api.get('/platform/companies', { params: { per_page: 200 } })
    const items = data?.data?.items || data?.data || []
    for (const company of items) {
      if (company?.id && company?.name) companyCache.set(company.id, company.name)
    }
  } catch {
    // Les IDs bruts restent affichés en fallback (résilience).
  }
}

async function loadPending() {
  loading.value = true
  try {
    const { data } = await api.get('/platform/payroll/tax-rates/pending')
    pendingSlabs.value = data.data.tax_slabs || []
    pendingContributions.value = data.data.social_contributions || []
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Impossible de charger les validations en attente.')
  } finally {
    loading.value = false
  }
}

async function loadSlabs() {
  loading.value = true
  try {
    const { data } = await api.get('/platform/payroll/tax-rates/tax-slabs', {
      params: { status: slabFilter.status || undefined, country_code: slabFilter.country_code || undefined },
    })
    slabs.value = data.data || []
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Impossible de charger les barèmes.')
  } finally {
    loading.value = false
  }
}

async function loadHistory() {
  try {
    const { data } = await api.get('/platform/payroll/tax-rates/history')
    history.value = data.data || []
  } catch (err) {
    toast.error(err?.response?.data?.message || "Impossible de charger l'historique.")
  }
}

async function approve(kind, item) {
  if (!window.confirm(`Approuver la modification « ${item.name} » (${item.country_code}) ?`)) return
  acting.value = true
  try {
    const path =
      kind === 'slab'
        ? `/platform/payroll/tax-rates/tax-slabs/${item.id}/approve`
        : `/platform/payroll/tax-rates/social-contributions/${item.id}/approve`
    await api.put(path)
    toast.success('Modification approuvée — désormais active pour les calculs de paie.')
    await Promise.all([loadPending(), loadSlabs(), loadHistory()])
  } catch (err) {
    toast.error(err?.response?.data?.message || "Impossible d'approuver.")
  } finally {
    acting.value = false
  }
}

function openReject(kind, item) {
  rejectTarget.value = { kind, item }
  rejectReason.value = ''
  rejectModal.value = true
}

async function submitReject() {
  if (!rejectTarget.value) return
  acting.value = true
  try {
    const { kind, item } = rejectTarget.value
    const path =
      kind === 'slab'
        ? `/platform/payroll/tax-rates/tax-slabs/${item.id}/reject`
        : `/platform/payroll/tax-rates/social-contributions/${item.id}/reject`
    await api.put(path, { reason: rejectReason.value })
    rejectModal.value = false
    toast.success('Modification rejetée — retournée en brouillon.')
    await Promise.all([loadPending(), loadSlabs(), loadHistory()])
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Impossible de rejeter.')
  } finally {
    acting.value = false
  }
}

function formatDate(value) {
  if (!value) return '—'
  try {
    return new Date(value).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
  } catch {
    return value
  }
}

onMounted(async () => {
  await loadCompanies()
  await Promise.all([loadPending(), loadSlabs(), loadHistory()])
})
</script>

const STATUS_STYLES = {
  active: 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300',
  pending_validation: 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300',
  draft: 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
  superseded: 'bg-sky-100 dark:bg-sky-900 text-sky-700 dark:text-sky-300',
}

const STATUS_LABELS = {
  active: '🟢 Active',
  pending_validation: '🟡 En attente',
  draft: '⚪ Brouillon',
  superseded: '🔵 Remplacée',
}

const HISTORY_LABELS = {
  created: 'Création',
  submitted: 'Soumission',
  approved: 'Approbation',
  rejected: 'Rejet',
  superseded: 'Remplacée',
}

function statusBadge(status) {
  return `px-2 py-0.5 rounded-full text-xs font-semibold ${STATUS_STYLES[status] || STATUS_STYLES.draft}`
}

function statusLabel(status) {
  return STATUS_LABELS[status] || status
}

function historyBadge() {
  return 'px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'
}

function historyLabel(action) {
  return HISTORY_LABELS[action] || action
}
