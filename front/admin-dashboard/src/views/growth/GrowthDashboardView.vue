<template>
  <div class="p-6">
    <div class="mb-8 flex justify-between items-center">
      <div>
        <h1 class="text-3xl font-bold text-slate-900">{{ t('growth.title', 'Administration Growth') }}</h1>
        <p class="text-slate-500 mt-1">{{ t('growth.subtitle', 'Pilotez le programme partenaire et les commissions Leopardo RH.') }}</p>
      </div>
    </div>

    <div class="flex gap-4 mb-8">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="currentTab = tab.id"
        :class="[
          'px-4 py-2 rounded-xl font-bold transition-all text-sm uppercase tracking-tight',
          currentTab === tab.id ? 'bg-teal-600 text-white shadow-lg shadow-teal-500/20' : 'glass-card text-slate-500 hover:bg-slate-50 shadow-sm border border-slate-200'
        ]"
      >
        {{ tab.label }}
      </button>
    </div>

    <div v-if="loading" class="p-12 text-center text-slate-500 font-bold uppercase tracking-widest animate-pulse">
      {{ t('growth.syncing', 'Synchronisation des données...') }}
    </div>

    <div v-if="loadError" class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-900/30 dark:bg-red-950/20 dark:text-red-400">
      {{ loadError }}
      <button class="ml-3 underline" @click="loadData">{{ t('growth.retry', 'Réessayer') }}</button>
    </div>

    <div v-else>
      <div v-if="currentTab === 'partners'" class="space-y-6">
        <div class="glass-card overflow-x-auto">
          <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">{{ t('growth.colPartner', 'Partenaire') }}</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">{{ t('growth.colRate', 'Taux HT (%)') }}</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">{{ t('growth.colReferred', 'Référés') }}</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">{{ t('growth.colStatus', 'Statut') }}</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">{{ t('growth.colActions', 'Actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 glass-card">
              <tr v-for="partner in partners" :key="partner.id" class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                  <div class="font-bold text-slate-900">{{ partner.user.first_name }} {{ partner.user.last_name }}</div>
                  <div class="text-xs text-slate-500 font-medium">{{ partner.user.email }}</div>
                </td>
                <td class="px-6 py-4 text-sm font-black text-teal-600">
                  {{ partner.default_commission_rate != null ? (partner.default_commission_rate / 100) : 0 }}%
                </td>
                <td class="px-6 py-4 text-sm font-bold text-slate-700">
                  {{ partner.referred_companies_count || 0 }}
                </td>
                <td class="px-6 py-4">
                  <span :class="getStatusClass(partner.application_status)" class="px-2.5 py-1 text-[10px] font-black rounded-full uppercase tracking-tighter">
                    {{ partner.application_status }}
                  </span>
                </td>
                <td class="px-6 py-4 flex gap-2">
                  <button
                    v-if="partner.application_status === 'pending'"
                    @click="approvePartner(partner)"
                    class="bg-emerald-500 text-white px-3 py-1 rounded-lg text-xs font-bold hover:bg-emerald-600 transition-colors"
                  >
                    {{ t('growth.approve', 'Approuver') }}
                  </button>
                  <span class="text-xs font-semibold text-slate-400">{{ statusLabel(partner.application_status) }}</span>
                </td>
              </tr>
              <tr v-if="partners.length === 0 && !loadError">
                <td colspan="5" class="px-6 py-12 text-center text-slate-500 italic">{{ t('growth.noPartners', 'Aucun partenaire trouvé.') }}</td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>

      <div v-if="currentTab === 'payouts'" class="space-y-6">
        <div class="glass-card overflow-x-auto">
           <table class="w-full text-left">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">{{ t('growth.colPartner', 'Partenaire') }}</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">{{ t('growth.colAmount', 'Montant') }}</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">{{ t('growth.colDate', 'Date') }}</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">{{ t('growth.colStatus', 'Statut') }}</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">{{ t('growth.colActions', 'Actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 glass-card">
              <tr v-for="payout in payouts" :key="payout.id">
                <td class="px-6 py-4 font-bold text-slate-900">{{ payout.partner?.user?.first_name }}</td>
                <td class="px-6 py-4 font-black text-slate-900">{{ (payout.amount / 100).toFixed(2) }} {{ payout.currency }}</td>
                <td class="px-6 py-4 text-sm text-slate-500">{{ formatDate(payout.created_at) }}</td>
                <td class="px-6 py-4">
                  <span :class="getStatusClass(payout.status)" class="px-2.5 py-1 text-[10px] font-black rounded-full uppercase tracking-tighter">
                    {{ payout.status }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <button v-if="payout.status === 'pending'" @click="updatePayout(payout, 'paid')" class="text-teal-600 font-bold text-xs uppercase underline">{{ t('growth.markPaid', 'Marquer Payé') }}</button>
                </td>
              </tr>
               <tr v-if="payouts.length === 0">
                <td colspan="5" class="px-6 py-12 text-center text-slate-500 italic">{{ t('growth.noPayouts', 'Aucune demande de paiement.') }}</td>
              </tr>
            </tbody>
           </table>
        </div>
      </div>

      <div v-if="currentTab === 'audit'" class="space-y-6">
        <div class="glass-card p-6 bg-slate-900 text-teal-400 font-mono text-xs overflow-auto max-h-[500px]">
          <div v-for="log in auditLogs" :key="log.id" class="mb-2 border-b border-white/5 pb-2">
            <span class="text-slate-500">[{{ log.created_at }}]</span>
            <span class="text-white"> {{ log.event }}</span>
            <span class="ml-2">Admin #{{ log.admin_id }} modified {{ log.auditable_type }} #{{ log.auditable_id }}</span>
            <div class="text-slate-400 ml-4">Reason: {{ log.reason }}</div>
          </div>
          <div v-if="auditLogs.length === 0" class="text-center py-8 opacity-50">{{ t('growth.noAuditLogs', "Zéro log d'audit Growth pour le moment.") }}</div>
        </div>
      </div>
    </div>
  <!-- QA #2994 : dialog note de paiement (remplace le prompt() natif) -->
  <div v-if="payoutNoteOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="payoutNoteOpen = false">
    <div class="glass-card w-full max-w-md p-6">
      <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4">{{ t('growth.payoutNoteTitle', 'Note de paiement (Audit)') }}</h2>
      <textarea v-model="payoutNoteText" rows="3" class="form-input w-full" :placeholder="t('growth.payoutNotePlaceholder', 'Motif / référence de l\'audit...')"></textarea>
      <div class="mt-4 flex justify-end gap-2">
        <button class="btn-secondary" @click="payoutNoteOpen = false">{{ t('common.cancel', 'Annuler') }}</button>
        <button class="btn-primary" @click="submitPayoutNote" :disabled="!payoutNoteText.trim()">{{ t('growth.validate', 'Valider') }}</button>
      </div>
    </div>
  </div>
  <!-- QA #3493 : dialog approbation candidature (remplace le confirm() natif) -->
  <div v-if="approveDialogOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="approveDialogOpen = false">
    <div class="glass-card w-full max-w-md p-6">
      <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4">{{ t('growth.approveDialogTitle', 'Approuver la candidature') }}</h2>
      <p class="text-slate-600 dark:text-slate-300 mb-2">{{ t('growth.approveDialogBody', 'Voulez-vous approuver la candidature de') }}</p>
      <p class="font-bold text-slate-900 dark:text-white mb-4">{{ approveDialogTarget?.user?.email }}</p>
      <div class="mt-4 flex justify-end gap-2">
        <button class="btn-secondary" @click="approveDialogOpen = false">{{ t('common.cancel', 'Annuler') }}</button>
        <button class="btn-primary" @click="submitApprovePartner">{{ t('growth.approve', 'Approuver') }}</button>
      </div>
    </div>
  </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '@/services/api';
import { useToast } from 'vue-toastification';
import { useLocaleStore } from '@/stores/locale';
import { toIntlLocale, translate } from '@/i18n/index.js';

const localeStore = useLocaleStore();
// #4517 : dates au format de la locale active.
const formatDate = (value) =>
  new Intl.DateTimeFormat(toIntlLocale(localeStore.current), { dateStyle: 'medium' }).format(new Date(value));

const toast = useToast();

const t = (key, fallback = '') => translate(localeStore.current, key, fallback);

const currentTab = ref('partners');
const loading = ref(true);
const loadError = ref('')
const tabs = computed(() => [
  { id: 'partners', label: t('growth.tabPartners', 'Partenaires') },
  { id: 'payouts', label: t('growth.tabPayouts', 'Paiements') },
  { id: 'audit', label: t('growth.tabAudit', 'Audit Log') },
]);

const partners = ref([]);
const payouts = ref([]);
const auditLogs = ref([]);

const loadData = async () => {
  loading.value = true;
  try {
    // #3938 : déballage d'enveloppe normalisé — /partners et /payouts
    // renvoient {data:[...]}, /history renvoie {commissions, audit_logs}
    // (contrat GrowthAdminController). Un wrap défensif évite un crash de
    // rendu (v-for / .length) si le contrat backend évolue.
    const pResponse = await api.get('/platform/growth/partners', { _skipToast: true });
    partners.value = Array.isArray(pResponse.data?.data) ? pResponse.data.data : [];

    const hResponse = await api.get('/platform/growth/history', { _skipToast: true });
    auditLogs.value = Array.isArray(hResponse.data?.audit_logs) ? hResponse.data.audit_logs : [];

    const payResponse = await api.get('/platform/growth/payouts', { _skipToast: true });
    payouts.value = Array.isArray(payResponse.data?.data) ? payResponse.data.data : [];
  } catch (e) {
    console.error("Growth Admin: Data load failed", e);
    loadError.value = t('growth.loadError', 'Erreur lors du chargement des données Growth.');
  } finally {
    loading.value = false;
  }
};

function statusLabel(status) {
  const labels = {
    pending: t('growth.statusPending', 'En attente'),
    approved: t('growth.statusApproved', 'Approuvé'),
    rejected: t('growth.statusRejected', 'Rejeté'),
  }
  return labels[status] || status || '—'
}

const payoutNoteTarget = ref(null)
const payoutNoteStatus = ref('')
const payoutNoteOpen = ref(false)
const payoutNoteText = ref('')

async function submitPayoutNote() {
  const target = payoutNoteTarget.value
  const status = payoutNoteStatus.value
  const reason = payoutNoteText.value.trim()
  if (!target || !reason) { payoutNoteOpen.value = false; return }
  try {
    await api.patch(`/platform/growth/payouts/${target.id}`, { status, notes: reason })
    loadData()
    payoutNoteOpen.value = false
    payoutNoteText.value = ''
    toast.success(t('growth.payoutUpdated', 'Paiement mis à jour'))
  } catch (e) {
    console.error('Update payout failed:', e)
    toast.error(t('growth.errorPrefix', 'Erreur : ') + (e?.response?.data?.message || e.message))
  }
}

onMounted(loadData);

// QA #3493 : dialog in-app (remplace le confirm() natif — non i18n, bloque le rendu).
const approveDialogOpen = ref(false);
const approveDialogTarget = ref(null);

const approvePartner = (partner) => {
  approveDialogTarget.value = partner;
  approveDialogOpen.value = true;
};

const submitApprovePartner = async () => {
  const partner = approveDialogTarget.value;
  if (!partner) return;
  try {
    await api.patch(`/platform/growth/partners/${partner.id}/application`, { status: 'approved' });
    approveDialogOpen.value = false;
    approveDialogTarget.value = null;
    loadData();
  } catch (e) {
    console.error('Approve partner failed:', e)
    toast.error(t('growth.errorPrefix', 'Erreur : ') + (e?.response?.data?.message || e.message))
  }
};

const updatePayout = async (payout, status) => {
  // QA #2994 : plus de prompt() natif (non i18n, bloque le rendu) — dialog in-app.
  payoutNoteTarget.value = payout
  payoutNoteStatus.value = status
  payoutNoteText.value = ''
  payoutNoteOpen.value = true
};

const getStatusClass = (status) => {
  switch(status) {
    case 'approved':
    case 'active':
    case 'paid': return 'bg-emerald-100 text-emerald-700';
    case 'pending': return 'bg-amber-100 text-amber-700';
    case 'rejected': return 'bg-red-100 text-red-700';
    default: return 'bg-slate-100 text-slate-600';
  }
};
</script>

