<template>
  <div class="space-y-6 animate-fade-in">

    <!-- Header -->
    <div class="card overflow-hidden">
      <div class="bg-slate-900 px-8 py-8 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl -mr-32 -mt-32" />
        <div class="absolute bottom-0 left-0 w-48 h-48 bg-cyan-500/10 rounded-full blur-3xl -ml-24 -mb-24" />

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between relative z-10">
          <div>
            <h1 class="text-3xl font-black tracking-tight text-white uppercase">
              Edge Nodes
            </h1>
            <p class="mt-1 text-slate-400 text-base">
              Surveillance et gestion des nœuds Edge offline.
            </p>
          </div>
          <div class="mt-4 sm:mt-0 flex gap-3">
            <button
              class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-bold text-white bg-white/10 hover:bg-white/20 border border-white/10 transition-all"
              @click="refreshAll"
              :disabled="loading"
            >
              <ArrowPathIcon class="h-4 w-4 mr-2" :class="{ 'animate-spin': loading }" />
              Actualiser
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <StatsCard title="Total nodes"   :value="stats.total"   color="blue"   icon="ServerIcon" />
      <StatsCard title="En ligne"       :value="stats.online"  color="green"  icon="SignalIcon" />
      <StatsCard title="Hors ligne"     :value="stats.offline" color="red"    icon="SignalSlashIcon" />
      <StatsCard title="En attente sync" :value="stats.pending" color="yellow" icon="ArrowPathIcon" />
    </div>

    <!-- Table des nodes -->
    <div class="card">
      <div class="flex items-center justify-between mb-4">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-400">
          Nœuds enregistrés
        </p>
        <input
          v-model="search"
          type="search"
          placeholder="Rechercher un nœud…"
          class="px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 w-48"
        />
      </div>

      <!-- Loading -->
      <div v-if="loading" class="space-y-3">
        <div v-for="i in 3" :key="i" class="h-16 rounded-xl bg-slate-800 animate-pulse" />
      </div>

      <!-- Empty -->
      <div v-else-if="filteredNodes.length === 0" class="py-12 text-center text-slate-500">
        <ServerIcon class="h-10 w-10 mx-auto mb-3 opacity-40" />
        <p class="text-sm">Aucun nœud Edge enregistré.</p>
      </div>

      <!-- Rows -->
      <div v-else class="space-y-2">
        <div
          v-for="node in filteredNodes"
          :key="node.id"
          class="flex flex-col sm:flex-row sm:items-center gap-3 p-4 rounded-xl bg-slate-800/60 border border-slate-700/50 hover:border-slate-600 transition-all"
        >
          <!-- Status dot -->
          <div class="flex-shrink-0">
            <div
              :class="[
                'h-3 w-3 rounded-full',
                node.status === 'online'  ? 'bg-green-400 shadow-lg shadow-green-400/50' :
                node.status === 'warning' ? 'bg-yellow-400 shadow-lg shadow-yellow-400/50' :
                                              'bg-red-400'
              ]"
            />
          </div>

          <!-- Node info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-semibold text-sm text-white">{{ node.name }}</span>
              <span class="text-xs text-slate-400 font-mono">{{ node.node_id }}</span>
              <StatusBadge :status="node.status" />
            </div>
            <div class="mt-1 flex items-center gap-4 flex-wrap text-xs text-slate-500">
              <span>IP : {{ node.ip_address || '—' }}</span>
              <span>Tenant : {{ node.company_name }}</span>
              <span>Dernière sync : {{ formatRelative(node.last_seen_at) }}</span>
              <span v-if="node.pending_count > 0" class="text-yellow-400">
                {{ node.pending_count }} en file
              </span>
            </div>
          </div>

          <!-- Licence -->
          <div class="flex-shrink-0 text-center hidden sm:block">
            <p class="text-xs text-slate-500 mb-0.5">Licence</p>
            <span
              :class="[
                'text-xs font-bold px-2 py-0.5 rounded-full',
                node.license_valid
                  ? 'bg-green-500/15 text-green-400'
                  : 'bg-red-500/15 text-red-400'
              ]"
            >
              {{ node.license_valid ? 'Valide' : 'Expirée' }}
            </span>
            <p class="text-xs text-slate-600 mt-0.5">exp. {{ formatDate(node.license_expires_at) }}</p>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-2 flex-shrink-0">
            <button
              class="btn-icon"
              title="Forcer la synchronisation"
              @click="forceSync(node)"
              :disabled="node.status === 'offline' || syncingId === node.id"
            >
              <ArrowPathIcon
                class="h-4 w-4"
                :class="{ 'animate-spin': syncingId === node.id }"
              />
            </button>
            <button
              class="btn-icon btn-icon--danger"
              title="Révoquer ce nœud"
              @click="confirmRevoke(node)"
            >
              <XCircleIcon class="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Revoke confirm modal -->
    <Teleport to="body">
      <div
        v-if="revokeTarget"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
        @click.self="revokeTarget = null"
      >
        <div class="card max-w-sm w-full mx-4">
          <h3 class="text-lg font-black text-white mb-2">Révoquer ce nœud ?</h3>
          <p class="text-sm text-slate-400 mb-4">
            Le nœud <strong class="text-white">{{ revokeTarget.name }}</strong> sera
            déconnecté et ne pourra plus synchroniser.
          </p>
          <div class="flex gap-3">
            <button class="btn btn-danger flex-1" @click="revokeNode">Révoquer</button>
            <button class="btn btn-ghost flex-1" @click="revokeTarget = null">Annuler</button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
  ArrowPathIcon,
  ServerIcon,
  SignalIcon,
  SignalSlashIcon,
  XCircleIcon,
} from '@heroicons/vue/24/outline'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import api from '@/services/api.js'

// ─── State ────────────────────────────────────────────────────────────────
const nodes       = ref([])
const loading     = ref(false)
const search      = ref('')
const syncingId   = ref(null)
const revokeTarget = ref(null)

let pollInterval = null

// ─── Computed ─────────────────────────────────────────────────────────────
const filteredNodes = computed(() => {
  const q = search.value.toLowerCase()
  if (!q) return nodes.value
  return nodes.value.filter(
    (n) =>
      n.name.toLowerCase().includes(q) ||
      n.node_id.toLowerCase().includes(q) ||
      (n.company_name || '').toLowerCase().includes(q)
  )
})

const stats = computed(() => ({
  total   : nodes.value.length,
  online  : nodes.value.filter((n) => n.status === 'online').length,
  offline : nodes.value.filter((n) => n.status === 'offline').length,
  pending : nodes.value.reduce((s, n) => s + (n.pending_count || 0), 0),
}))

// ─── Lifecycle ────────────────────────────────────────────────────────────
onMounted(async () => {
  await fetchNodes()
  // Rafraîchissement automatique toutes les 60 s
  pollInterval = setInterval(fetchNodes, 60_000)
})

onUnmounted(() => clearInterval(pollInterval))

// ─── Methods ──────────────────────────────────────────────────────────────
async function fetchNodes() {
  try {
    loading.value = true
    const res = await api.get('/platform/edge/nodes')
    nodes.value = res.data?.data ?? res.data ?? []
  } catch (err) {
    console.error('[EdgeNodes] fetch failed', err)
  } finally {
    loading.value = false
  }
}

async function refreshAll() {
  await fetchNodes()
}

async function forceSync(node) {
  syncingId.value = node.id
  try {
    await api.post(`/platform/edge/nodes/${node.id}/sync`)
    node.last_seen_at = new Date().toISOString()
  } catch {
    // silently ignore — user sees no change
  } finally {
    syncingId.value = null
  }
}

function confirmRevoke(node) {
  revokeTarget.value = node
}

async function revokeNode() {
  if (!revokeTarget.value) return
  try {
    await api.delete(`/platform/edge/nodes/${revokeTarget.value.id}`)
    nodes.value = nodes.value.filter((n) => n.id !== revokeTarget.value.id)
  } finally {
    revokeTarget.value = null
  }
}

// ─── Formatters ───────────────────────────────────────────────────────────
function formatRelative(isoStr) {
  if (!isoStr) return 'jamais'
  const diff = Date.now() - new Date(isoStr).getTime()
  const mins = Math.floor(diff / 60_000)
  if (mins < 1)  return 'à l\'instant'
  if (mins < 60) return `il y a ${mins} min`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24)  return `il y a ${hrs} h`
  return `il y a ${Math.floor(hrs / 24)} j`
}

function formatDate(isoStr) {
  if (!isoStr) return '—'
  return new Date(isoStr).toLocaleDateString('fr-FR', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
}
</script>

<style scoped>
.btn-icon {
  @apply p-2 rounded-lg bg-slate-700 text-slate-300 hover:bg-slate-600 hover:text-white transition-all disabled:opacity-40 disabled:cursor-not-allowed;
}
.btn-icon--danger {
  @apply hover:bg-red-500/20 hover:text-red-400;
}
.btn {
  @apply px-4 py-2 rounded-xl font-bold text-sm transition-all;
}
.btn-danger {
  @apply bg-red-600 text-white hover:bg-red-500;
}
.btn-ghost {
  @apply bg-slate-700 text-slate-300 hover:bg-slate-600 hover:text-white;
}
</style>
