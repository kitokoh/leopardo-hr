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
          currentTab === tab.id ? 'bg-teal-600 text-white shadow-lg shadow-teal-500/20' : 'bg-white text-slate-500 hover:bg-slate-50'
        ]"
      >
        {{ tab.label }}
      </button>
    </div>

    <div v-if="currentTab === 'partners'" class="space-y-6">
      <!-- Partenaires (Table existante enrichie) -->
      <div class="glass-card overflow-hidden">
        <table class="w-full text-left">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Partenaire</th>
              <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Taux HT (%)</th>
              <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Coordonnées</th>
              <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Statut App.</th>
              <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 bg-white">
            <tr v-for="partner in partners" :key="partner.id">
              <td class="px-6 py-4">
                <div class="font-bold text-slate-900">{{ partner.user.first_name }} {{ partner.user.last_name }}</div>
                <div class="text-xs text-slate-500 font-medium">{{ partner.user.email }}</div>
              </td>
              <td class="px-6 py-4 text-sm font-black text-teal-600">
                {{ partner.default_commission_rate / 100 }}%
              </td>
              <td class="px-6 py-4">
                 <button class="text-xs font-bold text-slate-600 underline">Voir IBAN (Décripté)</button>
              </td>
              <td class="px-6 py-4">
                <span :class="partner.appStatusClass" class="px-2.5 py-1 text-xs font-black rounded-full uppercase tracking-tighter">
                  {{ partner.application_status }}
                </span>
              </td>
              <td class="px-6 py-4 flex gap-2">
                <button v-if="partner.application_status === 'pending'" class="bg-emerald-500 text-white px-3 py-1 rounded-lg text-xs font-bold">Approuver</button>
                <button class="text-slate-400 hover:text-teal-600 font-bold text-xs uppercase">Détails</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="currentTab === 'payouts'" class="space-y-6">
      <div class="glass-card p-6 text-center text-slate-500 font-medium italic">
        Module de validation des paiements : 12 demandes en attente.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const currentTab = ref('partners');
const tabs = [
  { id: 'partners', label: 'Partenaires' },
  { id: 'payouts', label: 'Demandes de Paiement' },
  { id: 'audit', label: 'Logs d\'Audit' },
];

const partners = ref([]);

onMounted(() => {
  partners.value = [
    {
      id: 1,
      user: { first_name: 'Jean', last_name: 'Partenaire', email: 'jean@partner.com' },
      default_commission_rate: 1500,
      application_status: 'pending',
      appStatusClass: 'bg-amber-100 text-amber-700'
    },
    {
      id: 2,
      user: { first_name: 'Sarah', last_name: 'Growth', email: 'sarah@agency.com' },
      default_commission_rate: 2000,
      application_status: 'approved',
      appStatusClass: 'bg-emerald-100 text-emerald-700'
    }
  ];
});
</script>
