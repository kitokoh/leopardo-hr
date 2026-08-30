<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          {{ $t('travel.contacts.title', 'Contacts voyageurs') }}
        </h2>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('travel.contacts.subtitle', 'Registre des demandes et consentements par canal (RGPD).') }}
        </p>
      </div>
      <div class="flex items-center gap-2">
        <router-link class="btn-secondary" :to="{ name: 'travel-contact-form' }">
          {{ $t('travel.contacts.openForm', 'Formulaire de contact') }}
        </router-link>
        <button class="btn-secondary" type="button" @click="load">
          {{ $t('travel.action.refresh', 'Actualiser') }}
        </button>
      </div>
    </div>

    <DataTable
      :columns="columns"
      :rows="contacts"
      :loading="loading"
      :error="listError"
      :search-keys="['first_name', 'last_name', 'email', 'phone']"
      :search-placeholder="$t('travel.contacts.searchPlaceholder', 'Rechercher un contact…')"
      :default-sort="defaultSortKey"
      :default-sort-dir="defaultSortDir"
      :caption="$t('travel.contacts.title', 'Contacts voyageurs')"
    >
      <template #cell-name="{ row }">
        {{ fullName(row) }}
      </template>
      <template #cell-consents="{ row }">
        <div class="flex flex-wrap gap-1">
          <span
            v-for="channel in consentChannels"
            :key="channel.key"
            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
            :class="consentBadgeClass(row, channel)"
          >
            {{ channel.label }}
          </span>
        </div>
      </template>
      <template #row-actions="{ row }">
        <div class="flex items-center justify-end gap-1">
          <button
            class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
            type="button"
            :aria-label="$t('travel.contacts.consentsAction', 'Gérer les consentements')"
            :title="$t('travel.contacts.consentsAction', 'Gérer les consentements')"
            @click="openConsents(row)"
          >
            {{ $t('travel.contacts.consentsAction', 'Consentements') }}
          </button>
          <button
            class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
            type="button"
            :aria-label="$t('travel.contacts.notifyAction', 'Envoyer une notification')"
            :title="$t('travel.contacts.notifyAction', 'Envoyer une notification')"
            @click="openNotify(row)"
          >
            {{ $t('travel.contacts.notifyAction', 'Notifier') }}
          </button>
        </div>
      </template>
    </DataTable>

    <!-- Gestion des consentements par canal -->
    <TravelModal
      :open="consentsOpen"
      :title="$t('travel.contacts.consentsTitle', 'Consentements par canal')"
      @close="closeConsents"
    >
      <div v-if="consentsError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">
        {{ consentsError }}
      </div>
      <form class="space-y-4" @submit.prevent="saveConsents">
        <p v-if="consentsTarget" class="text-sm text-slate-600 dark:text-slate-300">
          {{ fullName(consentsTarget) }}
          <span v-if="consentsTarget.email" class="text-slate-400"> — {{ consentsTarget.email }}</span>
        </p>
        <label
          v-for="channel in consentChannels"
          :key="channel.key"
          class="flex cursor-pointer items-center justify-between rounded-lg border border-slate-200/60 px-3 py-2.5 dark:border-slate-800/60"
        >
          <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ channel.label }}</span>
          <span class="flex items-center gap-2">
            <span v-if="consentsDraft[channel.given] && consentsDraft[channel.at]" class="text-xs text-slate-400">
              {{ formatDate(consentsDraft[channel.at]) }}
            </span>
            <input
              :checked="consentChecked(channel)"
              class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
              type="checkbox"
              :aria-label="channel.label"
              @change="toggleConsent(channel, $event)"
            />
          </span>
        </label>
        <p class="text-xs text-slate-400">
          {{ $t('travel.contacts.consentsHint', 'Aucune notification ne peut être envoyée sans consentement explicite du canal.') }}
        </p>
        <div class="flex justify-end gap-2">
          <button class="btn-secondary" type="button" @click="closeConsents">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button class="btn-primary" type="submit" :disabled="savingConsents">
            {{ savingConsents ? $t('common.busy', 'En cours…') : $t('travel.action.save', 'Enregistrer') }}
          </button>
        </div>
      </form>
    </TravelModal>

    <!-- Notification manuelle -->
    <TravelModal
      :open="notifyOpen"
      :title="$t('travel.contacts.notifyTitle', 'Notification manuelle')"
      wide
      @close="closeNotify"
    >
      <div v-if="notifyError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">
        {{ notifyError }}
      </div>
      <form class="space-y-4" @submit.prevent="sendNotify">
        <p v-if="notifyTarget" class="text-sm text-slate-600 dark:text-slate-300">
          {{ fullName(notifyTarget) }}
          <span v-if="notifyTarget.email" class="text-slate-400"> — {{ notifyTarget.email }}</span>
        </p>
        <div>
          <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="travel-notify-message">
            {{ $t('travel.contacts.messageLabel', 'Message') }}
          </label>
          <textarea
            id="travel-notify-message"
            v-model="notifyMessage"
            class="form-input mt-1"
            rows="4"
            maxlength="2000"
            :placeholder="$t('travel.contacts.messagePlaceholder', 'Votre message (2 000 caractères max)…')"
          ></textarea>
        </div>
        <div>
          <span class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            {{ $t('travel.contacts.channelsLabel', 'Canaux') }}
          </span>
          <div class="mt-2 flex gap-4">
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
              <input v-model="notifyChannels" class="h-4 w-4 rounded border-slate-300 text-brand-600" type="checkbox" value="email" />
              {{ $t('travel.contacts.channelEmail', 'Email') }}
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
              <input v-model="notifyChannels" class="h-4 w-4 rounded border-slate-300 text-brand-600" type="checkbox" value="app" />
              {{ $t('travel.contacts.channelApp', 'In-app') }}
            </label>
          </div>
        </div>
        <div class="flex justify-end gap-2">
          <button class="btn-secondary" type="button" @click="closeNotify">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button class="btn-primary" type="submit" :disabled="sendingNotify">
            {{ sendingNotify ? $t('common.busy', 'En cours…') : $t('travel.contacts.sendNotify', 'Envoyer') }}
          </button>
        </div>
      </form>
    </TravelModal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import TravelModal from '@/components/travel/TravelModal.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const toast = useToast()

const contacts = ref([])
const loading = ref(false)
const listError = ref('')

const defaultSortKey = 'created_at'
const defaultSortDir = 'desc' 

const consentsOpen = ref(false)
const consentsTarget = ref(null)
const consentsDraft = ref({})
const consentsError = ref('')
const savingConsents = ref(false)

const notifyOpen = ref(false)
const notifyTarget = ref(null)
const notifyMessage = ref('')
const notifyChannels = ref(['email'])
const notifyError = ref('')
const sendingNotify = ref(false)

const consentChannels = computed(() => [
  {
    key: 'email',
    label: t('travel.contacts.channelEmail', 'Email'),
    given: 'email_consent_given',
    at: 'email_consent_at',
    onClass: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    offClass: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
  },
  {
    key: 'sms',
    label: t('travel.contacts.channelSms', 'SMS'),
    given: 'sms_consent_given',
    at: 'sms_consent_at',
    onClass: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    offClass: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
  },
  {
    key: 'whatsapp',
    label: t('travel.contacts.channelWhatsapp', 'WhatsApp'),
    given: 'whatsapp_consent_given',
    at: 'whatsapp_consent_at',
    onClass: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    offClass: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
  }
])

const columns = computed(() => [
  { key: 'name', label: t('travel.contacts.colName', 'Nom') },
  { key: 'email', label: t('travel.contacts.colEmail', 'Email') },
  { key: 'phone', label: t('travel.contacts.colPhone', 'Téléphone') },
  { key: 'consents', label: t('travel.contacts.colConsents', 'Consentements') },
  { key: 'created_at', label: t('travel.contacts.colCreated', 'Contact depuis') }
])

function fullName(row) {
  const parts = [row.first_name, row.last_name].filter(Boolean)
  return parts.length > 0 ? parts.join(' ') : (row.email || `#${row.id}`)
}

function consentBadgeClass(row, channel) {
  return row[channel.given] ? channel.onClass : channel.offClass
}

function consentChecked(channel) {
  return Boolean(consentsDraft.value[channel.given])
}

function toggleConsent(channel, event) {
  consentsDraft.value[channel.given] = event.target.checked
}

function formatDate(value) {
  if (!value) return ''
  try {
    return new Date(value).toLocaleDateString(localeStore.current === 'en' ? 'en-GB' : 'fr-FR')
  } catch {
    return String(value)
  }
}

async function load() {
  loading.value = true
  listError.value = ''
  try {
    const res = await api.get('/travel/contacts', { params: { per_page: 200 }, _skipAuthRedirect: true })
    contacts.value = res.data?.data || []
  } catch (err) {
    listError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    loading.value = false
  }
}

/* ── consentements ─────────────────────────────────────────── */
function openConsents(row) {
  consentsTarget.value = row
  consentsDraft.value = {
    email_consent_given: Boolean(row.email_consent_given),
    email_consent_at: row.email_consent_at || '',
    sms_consent_given: Boolean(row.sms_consent_given),
    sms_consent_at: row.sms_consent_at || '',
    whatsapp_consent_given: Boolean(row.whatsapp_consent_given),
    whatsapp_consent_at: row.whatsapp_consent_at || ''
  }
  consentsError.value = ''
  consentsOpen.value = true
}

function closeConsents() {
  consentsOpen.value = false
}

async function saveConsents() {
  if (!consentsTarget.value) return
  savingConsents.value = true
  consentsError.value = ''
  try {
    await api.put(`/travel/contacts/${consentsTarget.value.id}/consent`, {
      email_consent_given: consentsDraft.value.email_consent_given,
      sms_consent_given: consentsDraft.value.sms_consent_given,
      whatsapp_consent_given: consentsDraft.value.whatsapp_consent_given
    }, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    consentsOpen.value = false
    await load()
  } catch (err) {
    consentsError.value = err.response?.data?.message || t('travel.error.saveFailed', "Échec de l'enregistrement.")
  } finally {
    savingConsents.value = false
  }
}

/* ── notification manuelle ─────────────────────────────────── */
function openNotify(row) {
  notifyTarget.value = row
  notifyMessage.value = ''
  notifyChannels.value = ['email']
  notifyError.value = ''
  notifyOpen.value = true
}

function closeNotify() {
  notifyOpen.value = false
}

async function sendNotify() {
  if (!notifyTarget.value || !notifyMessage.value.trim()) return
  sendingNotify.value = true
  notifyError.value = ''
  try {
    await api.post(`/travel/contacts/${notifyTarget.value.id}/notify`, {
      message: notifyMessage.value.trim(),
      channels: notifyChannels.value
    }, { _skipAuthRedirect: true })
    toast.success(t('travel.contacts.notifySent', 'Notification envoyée.'))
    notifyOpen.value = false
  } catch (err) {
    // 422 : aucun canal consenti — message backend explicite.
    notifyError.value = err.response?.data?.message || t('travel.error.actionFailed', "L'action a échoué.")
  } finally {
    sendingNotify.value = false
  }
}

onMounted(load)
</script>
