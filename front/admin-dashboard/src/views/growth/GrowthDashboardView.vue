<template>
  <div class="p-6">
    <div class="mb-8 flex justify-between items-center">
      <div>
        <h1 class="text-3xl font-bold text-slate-900">Administration Growth</h1>
        <p class="text-slate-500 mt-1">Pilotez le programme partenaire et les commissions Leopardo RH.</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
      <div v-for="metric in metrics" :key="metric.label" class="glass-card p-6">
        <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wider">{{ metric.label }}</h3>
        <p class="text-2xl font-bold text-slate-900 mt-1">{{ metric.value }}</p>
      </div>
    </div>

    <div class="glass-card overflow-hidden">
      <div class="p-6 border-b border-slate-200 flex justify-between items-center bg-slate-50/50">
        <h2 class="text-lg font-semibold text-slate-900">Gestion des Partenaires</h2>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Partenaire</th>
              <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Taux (%)</th>
              <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Référés</th>
              <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Dernière Audit</th>
              <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="partner in partners" :key="partner.id">
              <td class="px-6 py-4">
                <div class="font-medium text-slate-900">{{ partner.user.first_name }} {{ partner.user.last_name }}</div>
                <div class="text-xs text-slate-500">{{ partner.user.email }}</div>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <span class="font-semibold">{{ partner.default_commission_rate / 100 }}%</span>
                  <button @click="editRate(partner)" class="text-slate-400 hover:text-teal-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                  </button>
                </div>
              </td>
              <td class="px-6 py-4 text-sm">{{ partner.referred_companies_count }}</td>
              <td class="px-6 py-4 text-xs text-slate-500">
                {{ partner.last_audit || 'Aucune modification' }}
              </td>
              <td class="px-6 py-4">
                <button class="text-teal-600 hover:text-teal-800 font-medium">Historique</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const metrics = ref([
  { label: 'Partenaires', value: '...' },
  { label: 'Total Commissions', value: '...' },
  { label: 'Audit Logs (30j)', value: '...' },
  { label: 'Alertes Fraude', value: '0' },
]);

const partners = ref([]);

onMounted(async () => {
  // Simulation d'appel à /api/v1/platform/growth/partners
  partners.value = [
    {
      id: 1,
      user: { first_name: 'Jean', last_name: 'Partenaire', email: 'jean@partner.com' },
      default_commission_rate: 1500,
      referred_companies_count: 8,
      last_audit: 'Taux mis à jour par Admin (12/06)'
    }
  ];

  metrics.value[0].value = partners.value.length;
  metrics.value[1].value = '12,450 €';
  metrics.value[2].value = '42';
});

const editRate = (partner) => {
  const newRate = prompt(`Nouveau taux pour ${partner.user.first_name} (en %) :`, partner.default_commission_rate / 100);
  if (newRate) {
    const reason = prompt("Raison du changement (Audit obligatoire) :");
    if (reason) {
      console.log(`Appel API PATCH /platform/growth/partners/${partner.id}/rate`, { rate: newRate * 100, reason });
      alert("Demande d'audit transmise au backend.");
    }
  }
};
</script>
