<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="$emit('close')">
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 w-full max-w-lg mx-4 shadow-2xl">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
          <h2 class="font-semibold text-gray-900 dark:text-white">Détails du node Edge</h2>
          <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
            ✕
          </button>
        </div>

        <!-- Content -->
        <div class="px-6 py-5 space-y-3 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-500">Node ID</span>
            <span class="font-mono text-xs text-gray-900 dark:text-white">{{ node.node_id }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Entreprise</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ node.company_name }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Statut</span>
            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', node.is_online ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600']">
              {{ node.is_online ? 'En ligne' : 'Hors ligne' }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Licence</span>
            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', licenseClass(node.license_status)]">
              {{ licenseLabel(node.license_status) }}
            </span>
          </div>
          <div v-if="node.license_expires_at" class="flex justify-between">
            <span class="text-gray-500">Expiration licence</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ formatDate(node.license_expires_at) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Dernière sync</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ node.last_sync_at ? formatDate(node.last_sync_at) : '—' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">En attente</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ node.pending_records }}</span>
          </div>
          <div v-if="node.ip_address" class="flex justify-between">
            <span class="text-gray-500">Adresse IP</span>
            <span class="font-mono text-xs text-gray-900 dark:text-white">{{ node.ip_address }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Enregistré le</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ formatDate(node.created_at) }}</span>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-700">
          <button @click="$emit('close')" class="px-4 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium transition-colors">Fermer</button>
          <button
            @click="$emit('sync', node); $emit('close')"
            :disabled="!node.is_online"
            class="px-4 py-2 text-sm rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
          >
            Synchroniser
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { useLocaleStore } from '@/stores/locale';
import { toIntlLocale } from '@/i18n/index.js';

const localeStore = useLocaleStore();

const props = defineProps({
  node: { type: Object, required: true },
});

defineEmits(['close', 'sync']);

function formatDate(iso) {
  return new Date(iso).toLocaleString(toIntlLocale(localeStore.current), {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

function licenseClass(status) {
  const map = {
    active: 'bg-green-100 text-green-700',
    expiring_soon: 'bg-yellow-100 text-yellow-700',
    expired: 'bg-red-100 text-red-700',
    revoked: 'bg-gray-100 text-gray-600',
  };
  return map[status] ?? 'bg-gray-100 text-gray-600';
}

function licenseLabel(status) {
  const map = {
    active: 'Active',
    expiring_soon: 'Expire bientôt',
    expired: 'Expirée',
    revoked: 'Révoquée',
  };
  return map[status] ?? status;
}
</script>
