<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="$emit('close')">
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 w-full max-w-lg mx-4 shadow-2xl">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
          <h2 class="font-semibold text-gray-900 dark:text-white">Détails du node Edge</h2>
          <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
            <span class="i-heroicons-x-mark w-5 h-5" />
          </button>
        </div>

        <!-- Content -->
        <div class="px-6 py-5 space-y-4">
          <Row label="Node ID" :value="node.node_id" mono />
          <Row label="Entreprise" :value="node.company_name" />
          <Row label="Statut" :value="node.is_online ? 'En ligne' : 'Hors ligne'" :badge="node.is_online ? 'green' : 'gray'" />
          <Row label="Licence" :value="node.license_status" :badge="licenseColor(node.license_status)" />
          <Row v-if="node.license_expires_at" label="Expiration licence" :value="formatDate(node.license_expires_at)" />
          <Row label="Dernière sync" :value="node.last_sync_at ? formatDate(node.last_sync_at) : '—'" />
          <Row label="Enregistrements en attente" :value="String(node.pending_records)" />
          <Row v-if="node.ip_address" label="Adresse IP locale" :value="node.ip_address" mono />
          <Row label="Enregistré le" :value="formatDate(node.created_at)" />
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-700">
          <button @click="$emit('close')" class="btn-secondary text-sm">Fermer</button>
          <button
            @click="$emit('sync', node); $emit('close')"
            :disabled="!node.is_online"
            class="btn-primary text-sm disabled:opacity-40 disabled:cursor-not-allowed"
          >
            Synchroniser maintenant
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
defineProps<{ node: EdgeNode }>();
defineEmits(['close', 'sync']);

function formatDate(iso: string) {
  return new Date(iso).toLocaleString('fr-FR', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

function licenseColor(status: string) {
  return { active: 'green', expiring_soon: 'yellow', expired: 'red', revoked: 'gray' }[status] ?? 'gray';
}
</script>

<!-- Row sub-component -->
<script lang="ts">
import { defineComponent, h } from 'vue';

const Row = defineComponent({
  props: {
    label: String,
    value: String,
    mono: Boolean,
    badge: String,
  },
  setup(props) {
    return () =>
      h('div', { class: 'flex items-center justify-between text-sm' }, [
        h('span', { class: 'text-gray-500 dark:text-gray-400' }, props.label),
        props.badge
          ? h('span', {
              class: `px-2 py-0.5 rounded-full text-xs font-medium ${
                { green: 'bg-green-100 text-green-700', yellow: 'bg-yellow-100 text-yellow-700', red: 'bg-red-100 text-red-700', gray: 'bg-gray-100 text-gray-600' }[props.badge!] ?? ''
              }`,
            }, props.value)
          : h('span', { class: `text-gray-900 dark:text-white ${props.mono ? 'font-mono text-xs' : 'font-medium'}` }, props.value),
      ]);
  },
});

export { Row };
</script>
