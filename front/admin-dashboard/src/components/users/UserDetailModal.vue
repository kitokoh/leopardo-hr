<template>
  <div ref="trapRef"
    class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-label="Modal" @keydown.escape="$emit('close')">
    <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        @click="$emit('close')"
      ></div>

      <!-- Modal panel -->
      <div class="inline-block transform overflow-hidden glass-card rounded-xl text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:align-middle">
        <div class="bg-white">
          <!-- Header -->
          <div class="border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-4">
                <div class="h-16 w-16 rounded-xl bg-brand-100 flex items-center justify-center text-lg font-black text-brand-700">
                  {{ initials(user?.name) }}
                </div>
                <div>
                  <h3 class="text-xl font-semibold text-gray-900">{{ user?.name }}</h3>
                  <p class="text-sm text-gray-500">{{ user?.email }}</p>
                  <div class="flex items-center space-x-2 mt-1">
                    <span
                      :class="[
                        'inline-flex rounded-full px-2 text-xs font-semibold leading-5',
                        getStatusColor(user?.status)
                      ]"
                    >
                      {{ getStatusLabel(user?.status) }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="flex items-center space-x-3">
                <button
                  @click="$emit('impersonate', user)"
                  class="inline-flex items-center px-3 py-2 border border-blue-300 shadow-sm text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100"
                >
                  <UserIcon class="h-4 w-4 mr-2" />
                  Impersonner
                </button>
                <button
                  @click="$emit('close')"
                  class="rounded-md bg-white text-gray-400 hover:text-gray-500"
                >
                  <XMarkIcon class="h-6 w-6" />
                </button>
              </div>
            </div>
          </div>

          <!-- Content -->
          <div class="px-6 py-6">
            <!-- Informations reelles (issue #2184) -->
            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="text-lg font-medium text-gray-900 mb-4">Informations générales</h4>
              <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <dt class="text-sm font-medium text-gray-500">ID Utilisateur</dt>
                  <dd class="mt-1 text-sm text-gray-900">#{{ user?.id }}</dd>
                </div>
                <div>
                  <dt class="text-sm font-medium text-gray-500">Entreprise</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ user?.company?.name || user?.company_name || 'Aucune' }}</dd>
                </div>
                <div>
                  <dt class="text-sm font-medium text-gray-500">ID Entreprise</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ user?.company?.id || user?.company_id || '-' }}</dd>
                </div>
                <div v-if="user?.company?.employee_id">
                  <dt class="text-sm font-medium text-gray-500">Employé lié</dt>
                  <dd class="mt-1 text-sm text-gray-900">#{{ user.company.employee_id }}</dd>
                </div>
                <div>
                  <dt class="text-sm font-medium text-gray-500">Date d'inscription</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ formatDate(user?.created_at) }}</dd>
                </div>
              </dl>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useFocusTrap } from '@/composables/useFocusTrap'

// WCAG 2.1.1/2.1.2 (issue #5622) — piéger le focus dans le modal.
const _trapActive = ref(true)
const { containerRef: trapRef } = useFocusTrap(_trapActive)

import { UserIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { translate, toIntlLocale } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale'

defineProps({
  user: {
    type: Object,
    required: true
  }
})

const localeStore = useLocaleStore()
// Convention repo : alias `t` pour la garde check-i18n-diff (PA2-I18N-014).
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

defineEmits(['close', 'impersonate'])

function initials(name) {
  return String(name || '?')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join('')
}

function getStatusColor(status) {
  const colors = {
    active: 'bg-emerald-100 text-emerald-800',
    inactive: 'bg-slate-100 text-slate-700',
    suspended: 'bg-red-100 text-red-800',
    pending: 'bg-amber-100 text-amber-800'
  }
  return colors[status] || 'bg-slate-100 text-slate-700'
}

function getStatusLabel(status) {
  // #4716 : libellés de statut localisés (avant : FR codé en dur dans les 4 locales).
  const labels = {
    active: t('time.statusActive', 'Actif'),
    inactive: t('time.statusInactive', 'Inactif'),
    suspended: t('time.statusSuspended', 'Suspendu'),
    pending: t('time.statusPending', 'Attente')
  }
  return labels[status] || status
}

function formatDate(date) {
  // #4714 : date localisée selon la locale active du cockpit (avant :
  // toLocaleDateString() sans locale → format du navigateur, incohérent
  // avec le reste de l'UI).
  if (!date) return '-'
  return new Date(date).toLocaleDateString(toIntlLocale(localeStore.current))
}
</script>
