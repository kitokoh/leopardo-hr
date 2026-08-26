<template>
  <div class="p-6 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Nodes Edge</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
          Gestion des nodes Edge Leopardo — synchronisation offline-first
        </p>
      </div>
      <button
        @click="refresh"
        :disabled="loading"
        class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <span :class="['inline-block w-4 h-4', loading ? 'animate-spin' : '']">↻</span>
        Actualiser
      </button>
    </div>

    <!-- Error banner (QA 2026-08-15, #2658) -->
    <div
      v-if="loadError"
      class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-300"
      role="alert"
    >
      {{ loadError }}
    </div>

    <!-- Stats row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <EdgeStatCard :label="t('edge.statTotal', 'Nodes total')" :value="stats.total" icon="🖥️" color="indigo" />
      <EdgeStatCard :label="t('edge.statOnline', 'En ligne')" :value="stats.online" icon="✅" color="green" />
      <EdgeStatCard :label="t('edge.statOffline', 'Hors ligne')" :value="stats.offline" icon="⭕" color="gray" />
      <EdgeStatCard :label="t('edge.statExpiredLicenses', 'Licences expirées')" :value="stats.licenseExpired" icon="⚠️" color="red" />
    </div>

    <!-- Nodes table -->
    <div class="glass-card dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div v-if="loading && nodes.length === 0" class="p-12 text-center text-gray-400">
        <div class="text-4xl mb-3 animate-spin inline-block">↻</div>
        <p>{{ t('edge.loading', 'Chargement des nodes…') }}</p>
      </div>

      <div v-else-if="!loading && nodes.length === 0" class="p-12 text-center text-gray-400">
        <div class="text-4xl mb-3 opacity-30">🖥️</div>
        <p class="font-medium">{{ t('edge.empty', 'Aucun node Edge enregistré') }}</p>
        <p class="text-sm mt-1">{{ t('edge.emptyHint', "Les nodes apparaissent ici une fois enregistrés via l'API Edge.") }}</p>
      </div>

      <div v-else class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="glass-bg dark:bg-slate-800/50 text-left text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
            <th class="px-4 py-3 font-medium">{{ t('edge.colNode', 'Node') }}</th>
            <th class="px-4 py-3 font-medium">{{ t('edge.colStatus', 'Statut') }}</th>
            <th class="px-4 py-3 font-medium">{{ t('edge.colLicense', 'Licence') }}</th>
            <th class="px-4 py-3 font-medium">{{ t('edge.colLastSync', 'Dernière sync') }}</th>
            <th class="px-4 py-3 font-medium">{{ t('edge.colActions', 'Actions') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          <tr
            v-for="node in nodes"
            :key="node.id"
            class="glass-bg-hover dark:hover:bg-slate-700/40 transition-colors"
          >
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900 dark:text-white font-mono text-xs">{{ node.id }}</div>
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
              <div v-if="!node.is_online && node.last_seen_at" class="text-xs text-red-400 mt-0.5">
                Vu {{ formatRelative(node.last_seen_at) }}
              </div>
            </td>

            <td class="px-4 py-3">
              <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', licenseClass(node.license_valid)]">
                {{ licenseLabel(node.license_valid) }}
              </span>
              <div v-if="node.license_expires_at" class="text-xs text-gray-400 mt-0.5">
                Exp. {{ formatDate(node.license_expires_at) }}
              </div>
            </td>

            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
              {{ node.last_sync_at ? formatRelative(node.last_sync_at) : '—' }}
            </td>

            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <button
                  @click="triggerSync(node)"
                  :disabled="!node.is_online || syncingNodeId === node.id"
                  class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-medium disabled:opacity-30 disabled:cursor-not-allowed"
                >
                  {{ syncingNodeId === node.id ? t('edge.syncing', 'Sync…') : t('edge.sync', 'Sync') }}
                </button>
                <span class="text-gray-300 dark:text-gray-600">|</span>
                <button @click="viewNode(node)" class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 font-medium">
                  {{ t('edge.details', 'Détails') }}
                </button>
                <!-- #4935 : bouton « Révoquer licence » branché sur le store
                     (endpoint POST /admin/edge-nodes/{id}/revoke existant) —
                     confirmation en 2 clics, réservé aux licences valides. -->
                <template v-if="node.license_valid">
                  <span class="text-gray-300 dark:text-gray-600">|</span>
                  <button
                    v-if="confirmRevokeId === node.id"
                    @click="revokeLicense(node)"
                    :disabled="revokingNodeId === node.id"
                    class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 font-medium disabled:opacity-30"
                  >
                    {{ revokingNodeId === node.id ? t('edge.revoking', 'Révoquer…') : t('edge.revokeConfirm', 'Confirmer la révocation ?') }}
                  </button>
                  <button
                    v-else
                    @click="confirmRevokeId = node.id"
                    class="text-xs text-red-500 hover:text-red-700 dark:text-red-400 font-medium"
                  >
                    {{ t('edge.revoke', 'Révoquer') }}
                  </button>
                </template>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      </div>
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
import { useToast } from 'vue-toastification';
import EdgeStatCard from '@/components/edge/EdgeStatCard.vue';
import EdgeNodeModal from '@/components/edge/EdgeNodeModal.vue';
import { useEdgeNodesStore } from '@/stores/edgeNodes';
import { useLocaleStore } from '@/stores/locale';
import { toIntlLocale, translate } from '@/i18n/index.js';

const store = useEdgeNodesStore();
const localeStore = useLocaleStore();
// Convention repo : alias `t` pour que la garde check-i18n-diff (PA2-I18N-014)
// reconnaisse les appels de traduction (pattern \bt\(['"]).
const t = (key, fallback = '') => translate(localeStore.current, key, fallback);
const loading = ref(false);
const syncingNodeId = ref(null);
const revokingNodeId = ref(null);
const confirmRevokeId = ref(null);
// QA 2026-08-15 (#2658) : état d'erreur visible (avant : rejections non
// gérées, aucun retour utilisateur).
const loadError = ref(null);
const toast = useToast();
const selectedNode = ref(null);
let refreshTimer = null;

const nodes = computed(() => store.nodes);
const stats = computed(() => ({
  total: nodes.value.length,
  online: nodes.value.filter((n) => n.is_online).length,
  offline: nodes.value.filter((n) => !n.is_online).length,
  licenseExpired: nodes.value.filter((n) => n.license_expires_at && new Date(n.license_expires_at) < new Date() && !n.license_valid).length,
}));

async function refresh() {
  loading.value = true;
  loadError.value = null;
  try {
    await store.fetchNodes();
  } catch (err) {
    loadError.value = err?.response?.data?.localized_message
      || err?.message
      || t('edge.loadError', 'Erreur lors du chargement des nodes Edge.');
    toast.error(loadError.value);
  } finally {
    loading.value = false;
  }
}

async function triggerSync(node) {
  syncingNodeId.value = node.id;
  loadError.value = null;
  try {
    await store.triggerSync(node.id);
    await refresh();
    toast.success(t('edge.syncSuccess', 'Synchronisation lancée pour {name}').replace('{name}', node.name || node.id));
  } catch (err) {
    loadError.value = err?.response?.data?.localized_message
      || err?.message
      || t('edge.syncError', 'Erreur lors du déclenchement de la synchronisation.');
    toast.error(loadError.value);
  } finally {
    syncingNodeId.value = null;
  }
}

function viewNode(node) {
  selectedNode.value = node;
}

// #4935 : révocation de licence edge node (store → POST /admin/edge-nodes/{id}/revoke).
async function revokeLicense(node) {
  revokingNodeId.value = node.id;
  loadError.value = null;
  try {
    await store.revokeLicense(node.id);
    await refresh();
    confirmRevokeId.value = null;
    toast.success(t('edge.revokeSuccess', 'Licence révoquée pour {name}').replace('{name}', node.name || node.id));
  } catch (err) {
    loadError.value = err?.response?.data?.localized_message
      || err?.message
      || t('edge.revokeError', 'Erreur lors de la révocation de la licence.');
    toast.error(loadError.value);
  } finally {
    revokingNodeId.value = null;
  }
}

function licenseClass(valid) {
  return valid
    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
    : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
}

function licenseLabel(valid) {
  // #4716 : libellés localisés (avant : FR codé en dur dans les 4 locales).
  return valid
    ? t('time.valid', 'Valide')
    : t('time.invalid', 'Invalide');
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString(toIntlLocale(localeStore.current), { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatRelative(iso) {
  // #4716 : temps relatif localisé (avant : FR codé en dur, visible dans les
  // 4 locales). Le date absolue reste le fallback au-delà de 24 h.
  const diff = Date.now() - new Date(iso).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return t('time.justNow', "à l'instant");
  if (mins < 60) {
    return t('time.minutesAgo', 'il y a {count} min').replace('{count}', mins);
  }
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) {
    return t('time.hoursAgo', 'il y a {count} h').replace('{count}', hrs);
  }
  return formatDate(iso);
}

onMounted(() => {
  refresh();
  refreshTimer = setInterval(refresh, 60_000);
});

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer);
});
</script>

