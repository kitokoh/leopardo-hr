<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('travel.contacts.title', 'Contacts voyageurs') }}
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('travel.contacts.subtitle', 'Registre des contacts, consentements par canal et notifications manuelles.') }}
      </p>
    </div>

    <TravelGate :mode="gateMode" :message="loadError" @retry="init" />

    <template v-if="!gateMode">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
          <input
            v-model="search"
            type="search"
            class="w-full max-w-xs rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            :placeholder="t('travel.contacts.searchPlaceholder', 'Rechercher (nom, email)…')"
            :aria-label="t('travel.common.search', 'Rechercher')"
            @keyup.enter="loadContacts"
          />
          <select
            v-model="consentFilter"
            class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            :aria-label="t('travel.contacts.consent', 'Consentement')"
            @change="loadContacts"
          >
            <option value="">{{ t('travel.contacts.allConsents', 'Tous les consentements') }}</option>
            <option value="email">Email</option>
            <option value="sms">SMS</option>
            <option value="whatsapp">WhatsApp</option>
          </select>
        </div>
      </div>

      <DataTable
        :columns="columns"
        :rows="contacts"
        :loading="loading"
        :error="listError"
        :search-keys="['first_name', 'last_name', 'email']"
        :search-placeholder="t('travel.common.search', 'Rechercher…')"
        :empty-message="t('travel.contacts.empty', 'Aucun contact')"
        key-field="id"
      >
        <template #cell-name="{ row }">
          <span class="text-sm">{{ [row.first_name, row.last_name].filter(Boolean).join(' ') || '—' }}</span>
        </template>
        <template #cell-consents="{ row }">
          <div class="flex flex-wrap gap-1">
            <span
              v-for="channel in consentChannels"
              :key="channel.key"
              class="rounded-full px-2 py-0.5 text-xs font-medium"
              :class="row[channel.key + '_consent_given'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
            >
              {{ channel.label }}
            </span>
          </div>
        </template>
        <template #row-actions="{ row }">
          <div class="flex justify-end gap-2">
            <button
              class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
              @click="openNotify(row)"
            >
              {{ t('travel.contacts.notify', 'Notifier') }}
            </button>
          </div>
        </template>
      </DataTable>

      <TravelFormModal
        :open="notifyModalOpen"
        :title="t('travel.contacts.notify', 'Notification manuelle')"
        :fields="notifyFormFields"
        :values="{ channels: ['email'] }"
        :busy="saving"
        :error="formError"
        @save="sendNotify"
        @close="notifyModalOpen = false"
      />
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/ui/DataTable.vue'
import TravelFormModal from '@/components/travel/TravelFormModal.vue'
import TravelGate from '@/components/travel/TravelGate.vue'
import { listTravel, travelList, notifyTravelContact } from '@/services/travel'

const { t } = useI18n()

const gateMode = ref('')
const loadError = ref('')
const contacts = ref([])
const loading = ref(false)
const listError = ref('')
const search = ref('')
const consentFilter = ref('')

const consentChannels = [
  { key: 'email', label: 'Email' },
  { key: 'sms', label: 'SMS' },
  { key: 'whatsapp', label: 'WhatsApp' },
]

const columns = [
  { key: 'name', label: t('travel.contacts.name', 'Nom'), sortable: true },
  { key: 'email', label: t('travel.contacts.email', 'Email'), sortable: true },
  { key: 'phone', label: t('travel.contacts.phone', 'Téléphone') },
  { key: 'consents', label: t('travel.contacts.consents', 'Consentements') },
]

async function loadContacts() {
  loading.value = true
  listError.value = ''
  try {
    const params = {}
    if (search.value.trim()) params.search = search.value.trim()
    if (consentFilter.value) params.consent = consentFilter.value
    const res = await listTravel('contacts', params)
    contacts.value = travelList(res)
  } catch (err) {
    listError.value = err?.response?.data?.message || String(err)
  } finally {
    loading.value = false
  }
}

const saving = ref(false)
const formError = ref('')
const notifyModalOpen = ref(false)
const activeContact = ref(null)

const notifyFormFields = [
  { key: 'message', label: 'Message', type: 'textarea', required: true, maxlength: 2000 },
  {
    key: 'channels', label: 'Canaux', type: 'select', required: true,
    options: [
      { value: 'email', label: 'Email' },
      { value: 'app', label: 'Application' },
    ],
  },
]

function openNotify(row) {
  activeContact.value = row
  formError.value = ''
  notifyModalOpen.value = true
}

async function sendNotify(values) {
  saving.value = true
  formError.value = ''
  try {
    await notifyTravelContact(activeContact.value.id, {
      message: values.message,
      channels: Array.isArray(values.channels) ? values.channels : [values.channels],
    })
    notifyModalOpen.value = false
  } catch (err) {
    formError.value = err?.response?.data?.message || String(err)
  } finally {
    saving.value = false
  }
}

function init() {
  gateMode.value = ''
  loadError.value = ''
  loadContacts()
}

onMounted(init)
</script>
