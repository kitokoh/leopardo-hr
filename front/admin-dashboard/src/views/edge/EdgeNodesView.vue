<template>
  <div class="p-6 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Nodes Edge</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
          Gestion des nodes Edge Leopardo â€” synchronisation offline-first
        </p>
      </div>
      <button
        @click="refresh"
        :disabled="loading"
        class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <span :class="['inline-block w-4 h-4', loading ? 'animate-spin' : '']">â†»</span>
        Actualiser
      </button>
    </div>

    <!-- Stats row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <EdgeStatCard label="Nodes total" :value="stats.total" icon="🖥️" color="indigo" />
      <EdgeStatCard label="En ligne" :value="stats.online" icon="✅" color="green" />
      <EdgeStatCard label="Hors ligne" :value="stats.offline" icon="⭕" color="gray" />
      <EdgeStatCard label="Licences expirÃ©es" :value="stats.licenseExpired" icon="⚠️" color="red" />
    </div>

    <!-- Nodes table -->
    <div class="glass-card dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div v-if="loading && nodes.length === 0" class="p-12 text-center text-gray-400">
        <div class="text-4xl mb-3 animate-spin inline-block">â†»</div>
        <p>Chargement des nodesâ€¦</p>
      </div>

      <div v-else-if="!loading && nodes.length === 0" class="p-12 text-center text-gray-400">
        <div class="text-4xl mb-3 opacity-30">🖥️</div>
        <p class="font-medium">Aucun node Edge enregistrÃ©</p>
        <p class="text-sm mt-1">Les nodes apparaissent ici une fois enregistrÃ©s via l'API Edge.</p>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="glass-bg dark:bg-gray-700/50 text-left text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
            <th class="px-4 py-3 font-medium">Node</th>
            <th class="px-4 py-3 font-medium">Statut</th>
            <th class="px-4 py-3 font-medium">Licence</th>
            <th class="px-4 py-3 font-medium">DerniÃ¨re sync</th>
            <th class="px-4 py-3 font-medium">En attente</th>
            <th class="px-4 py-3 font-medium">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          <tr
            v-for="node in nodes"
            :key="node.id"
            class="hover:glass-bg dark:hover:bg-gray-700/30 transition-colors"
          >
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900 dark:text-white font-mono text-xs">{{ node.node_id }}</div>
              <div class="text-xs text-gray-400 mt-0.5">{{ node.company_name }}</div>
            </td>

            <td class="px-4 py-3">
              <span
                :class="[
                  'inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium',
                  node.is_online
                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                    : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'
                ]"
              >
                <span :class="['w-1.5 h-1.5 rounded-full', node.is_online ? 'bg-green-500' : 'bg-gray-400']" />
                {{ node.is_online ? 'En ligne' : 'Hors ligne' }}
              </span>
              <div v-if="node.silent_since && !node.is_online" class="text-xs text-red-400 mt-0.5">
                Silencieux depuis {{ formatDuration(node.silent_since) }}
              </div>
            </td>

            <td class="px-4 py-3">
              <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', licenseClass(node.license_status)]">
                {{ licenseLabel(node.license_status) }}
              </span>
              <div v-if="node.license_expires_at" class="text-xs text-gray-400 mt-0.5">
                Exp. {{ formatDate(node.license_expires_at) }}
              </div>
            </td>

            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
              {{ node.last_sync_at ? formatRelative(node.last_sync_at) : 'â€”' }}
            </td>

            <td class="px-4 py-3">
              <span :class="['text-sm font-medium', node.pending_records > 0 ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-400']">
                {{ node.pending_records }}
              </span>
            </td>

            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <button
                  @click="triggerSync(node)"
                  :disabled="!node.is_online || syncingNodeId === node.id"
                  class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-medium disabled:opacity-30 disabled:cursor-not-allowed"
                >
                  {{ syncingNodeId === node.id ? 'Syncâ€¦' : 'Sync' }}
                </button>
                <span class="text-gray-300 dark:text-gray-600">|</span>
                <button @click="viewNode(node)" class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 font-medium">
                  DÃ©tails
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Node detail modal -->
    <EdgeNodeModal
      v-if="selectedNode"
      :node="selectedNode"
      @close="selectedNode = null"
      @sync="triggerSync"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import EdgeStatCard from '@/components/edge/EdgeStatCard.vue';
import EdgeNodeModal from '@/components/edge/EdgeNodeModal.vue';
import { useEdgeNodesStore } from '@/stores/edgeNodes';
import { useLocaleStore } from '@/stores/locale';
import { toIntlLocale } from '@/i18n/index.js';

const store = useEdgeNodesStore();
const localeStore = useLocaleStore();
const loading = ref(false);
const syncingNodeId = ref(null);
const selectedNode = ref(null);
let refreshTimer = null;

const nodes = computed(() => store.nodes);
const stats = computed(() => ({
  total: nodes.value.length,
  online: nodes.value.filter((n) => n.is_online).length,
  offline: nodes.value.filter((n) => !n.is_online).length,
  licenseExpired: nodes.value.filter((n) => n.license_status === 'expired').length,
}));

async function refresh() {
  loading.value = true;
  try {
    await store.fetchNodes();
  } finally {
    loading.value = false;
  }
}

async function triggerSync(node) {
  syncingNodeId.value = node.id;
  try {
    await store.triggerSync(node.id);
    await refresh();
  } finally {
    syncingNodeId.value = null;
  }
}

function viewNode(node) {
  selectedNode.value = node;
}

function licenseClass(status) {
  const map = {
    active: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    expiring_soon: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    expired: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    revoked: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
  };
  return map[status] ?? 'bg-gray-100 text-gray-600';
}

function licenseLabel(status) {
  const map = {
    active: 'Active',
    expiring_soon: 'Expire bientÃ´t',
    expired: 'ExpirÃ©e',
    revoked: 'RÃ©voquÃ©e',
  };
  return map[status] ?? status;
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString(toIntlLocale(localeStore.current), { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatRelative(iso) {
  const diff = Date.now() - new Date(iso).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return "Ã  l'instant";
  if (mins < 60) return `il y a ${mins} min`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `il y a ${hrs} h`;
  return formatDate(iso);
}

function formatDuration(iso) {
  const diff = Date.now() - new Date(iso).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 60) return `${mins} min`;
  const hrs = Math.floor(mins / 60);
  return `${hrs} h ${mins % 60} min`;
}

onMounted(() => {
  refresh();
  refreshTimer = setInterval(refresh, 60_000);
});

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer);
});
</script>

