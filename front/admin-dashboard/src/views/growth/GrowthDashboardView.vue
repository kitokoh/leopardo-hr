<template>
  <div class="p-6">
    <div class="mb-8 flex justify-between items-center">
      <div>
        <h1 class="text-3xl font-bold text-slate-900">Administration Growth</h1>
        <p class="text-slate-500 mt-1">Pilotez le programme partenaire et les commissions Leopardo RH.</p>
      </div>
    </div>

    <div class="flex gap-4 mb-8">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="currentTab = tab.id"
        :class="[
          'px-4 py-2 rounded-xl font-bold transition-all text-sm uppercase tracking-tight',
          currentTab === tab.id ? 'bg-teal-600 text-white shadow-lg shadow-teal-500/20' : 'bg-white text-slate-500 hover:bg-slate-50 shadow-sm border border-slate-200'
        ]"
      >
        {{ tab.label }}
      </button>
    </div>

    <div v-if="loading" class="p-12 text-center text-slate-500 font-bold uppercase tracking-widest animate-pulse">
      Synchronisation des données...
    </div>

    <div v-else>
      <div v-if="currentTab === 'partners'" class="space-y-6">
        <div class="glass-card overflow-hidden">
          <table class="w-full text-left">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Partenaire</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Taux HT (%)</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Référés</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Statut Candidature</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
              <tr v-for="partner in partners" :key="partner.id" class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                  <div class="font-bold text-slate-900">{{ partner.user.first_name }} {{ partner.user.last_name }}</div>
                  <div class="text-xs text-slate-500 font-medium">{{ partner.user.email }}</div>
                </td>
                <td class="px-6 py-4 text-sm font-black text-teal-600">
                  {{ partner.default_commission_rate / 100 }}%
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
                    Approuver
                  </button>
                  <button class="text-slate-400 hover:text-teal-600 font-bold text-xs uppercase">Gérer</button>
                </td>
              </tr>
              <tr v-if="partners.length === 0">
                <td colspan="5" class="px-6 py-12 text-center text-slate-500 italic">Aucun partenaire trouvé.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="currentTab === 'payouts'" class="space-y-6">
        <div class="glass-card overflow-hidden">
           <table class="w-full text-left">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Partenaire</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Montant</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Date</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Statut</th>
                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
              <tr v-for="payout in payouts" :key="payout.id">
                <td class="px-6 py-4 font-bold text-slate-900">{{ payout.partner?.user?.first_name }}</td>
                <td class="px-6 py-4 font-black text-slate-900">{{ (payout.amount / 100).toFixed(2) }} {{ payout.currency }}</td>
                <td class="px-6 py-4 text-sm text-slate-500">{{ new Date(payout.created_at).toLocaleDateString() }}</td>
                <td class="px-6 py-4">
                  <span :class="getStatusClass(payout.status)" class="px-2.5 py-1 text-[10px] font-black rounded-full uppercase tracking-tighter">
                    {{ payout.status }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <button v-if="payout.status === 'pending'" @click="updatePayout(payout, 'paid')" class="text-teal-600 font-bold text-xs uppercase underline">Marquer Payé</button>
                </td>
              </tr>
               <tr v-if="payouts.length === 0">
                <td colspan="5" class="px-6 py-12 text-center text-slate-500 italic">Aucune demande de paiement.</td>
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
          <div v-if="auditLogs.length === 0" class="text-center py-8 opacity-50">Zéro log d'audit Growth pour le moment.</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';

const currentTab = ref('partners');
const loading = ref(true);
const tabs = [
  { id: 'partners', label: 'Partenaires' },
  { id: 'payouts', label: 'Paiements' },
  { id: 'audit', label: 'Audit Log' },
];

const partners = ref([]);
const payouts = ref([]);
const auditLogs = ref([]);

const loadData = async () => {
  loading.value = true;
  try {
    const pResponse = await api.get('/platform/growth/partners');
    partners.value = pResponse.data.data;

    const hResponse = await api.get('/platform/growth/history');
    payouts.value = []; // Handled via payouts endpoint now
    auditLogs.value = hResponse.data.audit_logs;

    const payResponse = await api.get('/platform/growth/payouts');
    payouts.value = payResponse.data.data;
  } catch (e) {
    console.error("Growth Admin: Data load failed", e);
  } finally {
    loading.value = false;
  }
};

onMounted(loadData);

const approvePartner = async (partner) => {
  if (!confirm(`Approuver la candidature de ${partner.user.email} ?`)) return;
  try {
    await api.patch(`/platform/growth/partners/${partner.id}/application`, { status: 'approved' });
    loadData();
  } catch (e) { alert("Erreur: " + e.message); }
};

const updatePayout = async (payout, status) => {
  const reason = prompt("Note de paiement (Audit) :");
  if (!reason) return;
  try {
    await api.patch(`/platform/growth/payouts/${payout.id}`, { status, notes: reason });
    loadData();
  } catch (e) { alert("Erreur: " + e.message); }
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
