<template>
  <section class="space-y-4">
    <div>
      <h2 class="text-xl font-bold text-slate-900 dark:text-white">
        {{ $t('travel.contacts.title', 'Contacts voyageurs') }}
      </h2>
      <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
        {{ $t('travel.contacts.subtitle', 'Registre des contacts avec consentements par canal (RGPD) et notification manuelle.') }}
      </p>
    </div>

    <div v-if="globalError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
      {{ globalError }}
    </div>

    <DataTable
      :columns="columns"
      :rows="contacts"
      :loading="loading"
      :error="listError"
      :search-keys="['first_name', 'last_name', 'email', 'phone']"
      :search-placeholder="$t('travel.search.contact', 'Rechercher un contact…')"
      default-sort="id"
      default-sort-dir="desc"
      :caption="$t('travel.contacts.title', 'Contacts voyageurs')"
    >
      <template #cell-name="{ row }">
        {{ [row.first_name, row.last_name].filter(Boolean).join(' ') || '—' }}
      </template>
      <template #cell-consents="{ row }">
        <div class="flex flex-wrap gap-1">
          <span
            v-for="c in consentChips(row)"
            :key="c.channel"
            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
            :class="c.given ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
            :title="c.title"
          >
            {{ c.label }}
          </span>
        </div>
      </template>
      <template #row-actions="{ row }">
        <div class="flex items-center justify-end gap-1">
          <button
            class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
            type="button"
            :aria-label="$t('travel.contacts.manageConsent', 'Gérer les consentements')"
            @click="openConsent(row)"
          >
            {{ $t('travel.contacts.consent', 'Consentements') }}
          </button>
          <button
            class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
            type="button"
            :aria-label="$t('travel.contacts.notify', 'Notifier')"
            @click="openNotify(row)"
          >
            {{ $t('travel.contacts.notify', 'Notifier') }}
          </button>
        </div>
      </template>
    </DataTable>

    <!-- Gestion des consentements -->
    <TravelModal
      :open="consentOpen"
      :title="$t('travel.contacts.consentTitle', 'Consentements par canal')"
      @close="closeConsent"
    >
      <div v-if="targetContact" class="space-y-4">
        <p class="text-sm text-slate-600 dark:text-slate-300">
          {{ $t('travel.contacts.consentFor', 'Contact') }} :
          <span class="font-semibold">{{ targetContact.email }}</span>
        </p>
        <p class="text-xs text-slate-400">
          {{ $t('travel.contacts.consentHint', 'Chaque changement est horodaté. Un retrait de consentement (opt-out) met immédiatement fin à l\u2019utilisation du canal.') }}
        </p>
        <div
          v-for="ch in CHANNELS"
          :key="ch.key"
          class="flex items-center justify-between rounded-lg border border-slate-200/60 px-4 py-3 dark:border-slate-800/60"
        >
          <div>
            <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ ch.label }}</p>
          </div>
          <button
            type="button"
            role="switch"
            :aria-checked="ch.given"
            :class="[
              'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors',
              ch.given ? 'bg-green-500' : 'bg-slate-300 dark:bg-slate-600'
            ]"
            :disabled="consentSaving"
            @click="toggleConsent(ch)"
          >
            <span
              :class="[
                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                ch.given ? 'translate-x-6' : 'translate-x-1'
              ]"
            />
          </button>
        </div>
        <div v-if="consentError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">
          {{ consentError }}
        </div>
      </div>
    </TravelModal>

    <!-- Notification manuelle -->
    <TravelModal
      :open="notifyOpen"
      :title="$t('travel.contacts.notifyTitle', 'Notification manuelle')"
      @close="closeNotify"
    >
      <form class="grid grid-cols-1 gap-4" @submit.prevent="sendNotify">
        <FormField id="travel-contact-notify-message" :label="$t('travel.contacts.message', 'Message')" :error="notifyErrors.message" required>
          <textarea v-model="notifyMessage" class="form-input" rows="4" required :maxlength="2000"></textarea>
        </FormField>
        <fieldset>
          <legend class="text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $t('travel.contacts.channels', 'Canaux (respect du consentement)') }}
          </legend>
          <div class="mt-2 flex flex-wrap gap-4">
            <label v-for="ch in NOTIFY_CHANNELS" :key="ch" class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
              <input v-model="notifyChannels" type="checkbox" :value="ch" class="form-checkbox" />
              {{ ch === 'email' ? $t('travel.contacts.channelEmail', 'Email') : $t('travel.contacts.channelApp', 'Application') }}
            </label>
          </div>
        </fieldset>
        <div v-if="notifyError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">
          {{ notifyError }}
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="closeNotify">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="notifySaving">
            {{ notifySaving ? $t('common.busy', 'En cours…') : $t('travel.contacts.notify', 'Notifier') }}
          </button>
        </div>
      </form>
    </TravelModal>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import FormField from '@/components/common/FormField.vue'
import TravelModal from '@/components/travel/TravelModal.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const toast = useToast()
const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const CHANNELS = [
  { key: 'email', labelKey: 'travel.contacts.channelEmail' },
  { key: 'sms', labelKey: 'travel.contacts.channelSms' },
  { key: 'whatsapp', labelKey: 'travel.contacts.channelWhatsapp' },
]

const NOTIFY_CHANNELS = ['email', 'app']

const contacts = ref([])
const loading = ref(false)
const listError = ref('')
const globalError = ref('')

const consentOpen = ref(false)
const consentSaving = ref(false)
const consentError = ref('')
const targetContact = ref(null)

const notifyOpen = ref(false)
const notifySaving = ref(false)
const notifyError = ref('')
const notifyMessage = ref('')
const notifyChannels = ref(['email'])
const notifyErrors = ref({})

const columns = computed(() => [
  { key: 'id', label: t('travel.field.id', 'ID'), sortable: true },
  { key: 'name', label: t('travel.field.fullName', 'Nom complet'), sortable: true },
  { key: 'email', label: t('travel.field.email', 'Email'), sortable: true },
  { key: 'phone', label: t('travel.field.contactPhone', 'Téléphone') },
  { key: 'consents', label: t('travel.contacts.consents', 'Consentements') },
  { key: 'created_at', label: t('travel.field.createdAt', 'Créé le'), sortable: true },
])

function consentChips(row) {
  return CHANNELS.map((ch) => {
    const given = Boolean(row[`${ch.key}_consent_given`])
    return {
      channel: ch.key,
      given,
      label: t(ch.labelKey, ch.key),
      title: t(ch.labelKey, ch.key),
    }
  })
}

async function load() {
  loading.value = true
  listError.value = ''
  try {
    const res = await api.get('/travel/contacts', { params: { per_page: 100 }, _skipAuthRedirect: true })
    contacts.value = res.data?.data || []
  } catch (err) {
    listError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    loading.value = false
  }
}

function openConsent(row) {
  targetContact.value = row
  consentError.value = ''
  consentOpen.value = true
}

function closeConsent() {
  consentOpen.value = false
}

async function toggleConsent(channel) {
  if (!targetContact.value) return
  consentSaving.value = true
  consentError.value = ''
  try {
    // Endpoint réel : POST /travel/contacts/{id}/consent (bulk par canal).
    const next = { ...targetContact.value }
    next[`${channel.key}_consent_given`] = !next[`${channel.key}_consent_given`]
    await api.post(`/travel/contacts/${targetContact.value.id}/consent`, {
      email_consent: next.email_consent_given,
      sms_consent: next.sms_consent_given,
      whatsapp_consent: next.whatsapp_consent_given,
    }, { _skipAuthRedirect: true })
    targetContact.value = next
    toast.success(t('travel.toast.saved', 'Enregistré.'))
  } catch (err) {
    consentError.value = err.response?.data?.message || t('travel.error.saveFailed', "Échec de l'enregistrement.")
  } finally {
    consentSaving.value = false
  }
}

function openNotify(row) {
  targetContact.value = row
  notifyMessage.value = ''
  notifyChannels.value = ['email']
  notifyErrors.value = {}
  notifyError.value = ''
  notifyOpen.value = true
}

function closeNotify() {
  notifyOpen.value = false
}

async function sendNotify() {
  if (!targetContact.value) return
  notifySaving.value = true
  notifyError.value = ''
  notifyErrors.value = {}
  try {
    const res = await api.post(`/travel/contacts/${targetContact.value.id}/notify`, { message: notifyMessage.value, channels: notifyChannels.value }, { _skipAuthRedirect: true })
    const channels = res.data?.data?.channels || notifyChannels.value
    toast.success(t('travel.contacts.notifySent', 'Notification envoyée ({channels}).', { channels: channels.join(', ') }))
    notifyOpen.value = false
  } catch (err) {
    const status = err.response?.status
    const data = err.response?.data || {}
    if (status === 422) {
      if (data.errors) {
        notifyErrors.value = Object.fromEntries(Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]))
      } else {
        notifyError.value = t('travel.contacts.noConsentChannel', 'Aucun canal consenti pour ce contact (422).')
      }
    } else {
      notifyError.value = data.message || t('travel.error.actionFailed', "L'action a échoué.")
    }
  } finally {
    notifySaving.value = false
  }
}

onMounted(load)
</script>
