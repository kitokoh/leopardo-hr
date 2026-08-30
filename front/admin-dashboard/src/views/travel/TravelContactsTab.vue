<template>
  <div class="space-y-6">
<<<<<<< HEAD
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
=======
    <!-- Formulaire de contact → lead CRM (TRAVEL-416/#6068) : consentement obligatoire. -->
    <section class="glass-card rounded-2xl p-6">
      <h2 class="text-xl font-bold text-slate-900 dark:text-white">
        {{ t('travel.contacts.formTitle', 'Formulaire de contact') }}
      </h2>
      <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
        {{ t('travel.contacts.formSubtitle', 'Saisir une demande de contact — le consentement email est obligatoire.') }}
      </p>

      <form class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submitContact">
        <FormField
          :id="'travel-contact-first'"
          :label="t('travel.contacts.field.firstName', 'Prénom')"
          :error="contactErrors.first_name"
        >
          <template #default="{ id, ariaInvalid, describedBy }">
            <input :id="id" v-model.trim="contactForm.first_name" type="text" class="form-input" maxlength="120" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" />
          </template>
        </FormField>
        <FormField
          :id="'travel-contact-last'"
          :label="t('travel.contacts.field.lastName', 'Nom')"
          :error="contactErrors.last_name"
        >
          <template #default="{ id, ariaInvalid, describedBy }">
            <input :id="id" v-model.trim="contactForm.last_name" type="text" class="form-input" maxlength="120" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" />
          </template>
        </FormField>
        <FormField
          :id="'travel-contact-email'"
          :label="t('travel.contacts.field.email', 'Email')"
          :error="contactErrors.email"
          required
        >
          <template #default="{ id, ariaInvalid, describedBy }">
            <input :id="id" v-model.trim="contactForm.email" type="email" class="form-input" required maxlength="190" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" />
          </template>
        </FormField>
        <FormField
          :id="'travel-contact-phone'"
          :label="t('travel.contacts.field.phone', 'Téléphone')"
          :error="contactErrors.phone"
        >
          <template #default="{ id, ariaInvalid, describedBy }">
            <input :id="id" v-model.trim="contactForm.phone" type="tel" class="form-input" maxlength="40" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" />
          </template>
        </FormField>
        <FormField
          :id="'travel-contact-message'"
          :label="t('travel.contacts.field.message', 'Message')"
          :error="contactErrors.message"
          class="col-span-full"
          required
        >
          <template #default="{ id, ariaInvalid, describedBy }">
            <textarea :id="id" v-model.trim="contactForm.message" class="form-input" rows="4" required maxlength="2000" :aria-invalid="ariaInvalid" :aria-describedby="describedBy"></textarea>
          </template>
        </FormField>

        <label class="col-span-full flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
          <input v-model="contactForm.consent_email" type="checkbox" class="mt-0.5 h-4 w-4" required />
          <span>{{ t('travel.contacts.consentLabel', 'J’accepte d’être contacté par email au sujet de ma demande.') }}</span>
        </label>

        <div v-if="contactGlobalError" class="col-span-full rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
          {{ contactGlobalError }}
        </div>
        <div v-if="contactSuccess" class="col-span-full rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status">
          {{ contactSuccess }}
        </div>

        <div class="col-span-full flex justify-end gap-2">
          <button type="submit" class="btn-primary" :disabled="contactSaving">
            {{ contactSaving ? $t('common.busy', 'En cours…') : t('travel.contacts.action.submit', 'Envoyer la demande') }}
          </button>
        </div>
      </form>
    </section>

    <!-- Registre des contacts voyageurs + consentement par canal (TRAVEL-415/#6067,
         TRAVEL-910/#6113) : endpoints liste/consentement livrés avec le batch
         admin contacts — voir PR backend dédiée. -->
    <section class="space-y-4">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">
            {{ t('travel.contacts.registryTitle', 'Registre des contacts') }}
          </h2>
          <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            {{ t('travel.contacts.registrySubtitle', 'Consentements par canal (opt-in/opt-out horodaté) et notification manuelle.') }}
          </p>
        </div>
      </div>

      <div v-if="registryError" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700" role="alert">
        {{ registryError }}
      </div>

      <DataTable
        :columns="registryColumns"
        :rows="contacts"
        :loading="registryLoading"
        :error="registryError"
        :search-keys="['first_name', 'last_name', 'email', 'phone']"
        :search-placeholder="t('travel.search.contact', 'Rechercher un contact…')"
        :caption="t('travel.contacts.registryTitle', 'Registre des contacts')"
      >
        <template #cell-consents="{ row }">
          <div class="flex flex-wrap items-center gap-1.5">
            <button
              v-for="ch in consentChannels"
              :key="ch.key"
              type="button"
              class="rounded-full px-2.5 py-1 text-xs font-medium transition-colors"
              :class="row[`${ch.key}_consent_given`] ? consentOnClass : consentOffClass"
              :aria-label="t('travel.contacts.action.toggleConsent', 'Basculer le consentement') + ' ' + ch.label"
              :title="`${ch.label} — ${row[`${ch.key}_consent_given`] ? t('travel.contacts.consentOn', 'Consenti') : t('travel.contacts.consentOff', 'Non consenti')}`"
              @click="toggleConsent(row, ch)"
            >
              {{ ch.label }}
            </button>
          </div>
        </template>
        <template #row-actions="{ row }">
          <button
            class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
            type="button"
            :aria-label="t('travel.contacts.action.notify', 'Notifier')"
            :title="t('travel.contacts.action.notify', 'Notifier')"
            @click="openNotify(row)"
          >
            {{ t('travel.contacts.action.notify', 'Notifier') }}
          </button>
        </template>
      </DataTable>
    </section>

    <!-- Notification manuelle (TRAVEL-910/#6113) -->
    <TravelModal
      :open="notifyOpen"
      :title="t('travel.contacts.notifyTitle', 'Notification manuelle')"
      @close="closeNotify"
    >
      <form class="grid grid-cols-1 gap-4" @submit.prevent="sendNotify">
        <p class="text-sm text-slate-600 dark:text-slate-300">
          {{ t('travel.contacts.notifyTarget', 'Destinataire') }} :
          <strong>{{ notifyTarget }}</strong>
        </p>
        <FormField
          :id="'travel-notify-message'"
          :label="t('travel.contacts.field.message', 'Message')"
          :error="notifyErrors.message"
          required
        >
          <template #default="{ id, ariaInvalid, describedBy }">
            <textarea :id="id" v-model.trim="notifyForm.message" class="form-input" rows="4" required maxlength="2000" :aria-invalid="ariaInvalid" :aria-describedby="describedBy"></textarea>
          </template>
        </FormField>

        <fieldset>
          <legend class="text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ t('travel.contacts.field.channels', 'Canaux') }}
          </legend>
          <div class="mt-2 space-y-2">
            <label v-for="ch in notifyChannels" :key="ch.key" class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
              <input v-model="notifyForm.channels" type="checkbox" :value="ch.key" class="h-4 w-4" />
              {{ ch.label }}
            </label>
          </div>
          <p class="mt-1 text-xs text-slate-400">
            {{ t('travel.contacts.notifyHint', 'Un canal sans consentement est ignoré ; 422 si aucun canal consenti.') }}
          </p>
        </fieldset>

        <div v-if="notifyError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
          {{ notifyError }}
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="closeNotify">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="notifySaving">
            {{ notifySaving ? $t('common.busy', 'En cours…') : t('travel.contacts.action.send', 'Envoyer') }}
>>>>>>> origin/bc/bc24-travel-admin-ui
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
<<<<<<< HEAD
=======
import FormField from '@/components/common/FormField.vue'
>>>>>>> origin/bc/bc24-travel-admin-ui
import TravelModal from '@/components/travel/TravelModal.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const toast = useToast()

<<<<<<< HEAD
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
=======
/* ── formulaire de contact (public, POST /travel/contact) ─── */
const contactForm = ref({ consent_email: false })
const contactErrors = ref({})
const contactGlobalError = ref('')
const contactSuccess = ref('')
const contactSaving = ref(false)

async function submitContact() {
  contactSaving.value = true
  contactErrors.value = {}
  contactGlobalError.value = ''
  contactSuccess.value = ''
  try {
    await api.post('/travel/contact', contactForm.value, { _skipAuthRedirect: true })
    contactSuccess.value = t('travel.contacts.success', 'Demande reçue — merci, nous reviendrons vers vous.')
    contactForm.value = { consent_email: false }
  } catch (err) {
    const data = err.response?.data || {}
    if (data.errors) {
      contactErrors.value = Object.fromEntries(
        Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      )
    }
    contactGlobalError.value = data.message || data.localized_message || t('travel.error.saveFailed', "Échec de l'enregistrement.")
  } finally {
    contactSaving.value = false
  }
}

/* ── registre des contacts ─────────────────────────────────── */
const consentChannels = [
  { key: 'email', label: 'Email' },
  { key: 'sms', label: 'SMS' },
  { key: 'whatsapp', label: 'WhatsApp' }
]

const consentOnClass = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
const consentOffClass = 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'

const contacts = ref([])
const registryLoading = ref(false)
const registryError = ref('')

const registryColumns = computed(() => [
  { key: 'last_name', label: t('travel.contacts.field.lastName', 'Nom') },
  { key: 'first_name', label: t('travel.contacts.field.firstName', 'Prénom') },
  { key: 'email', label: t('travel.contacts.field.email', 'Email') },
  { key: 'phone', label: t('travel.contacts.field.phone', 'Téléphone') },
  { key: 'consents', label: t('travel.contacts.field.consents', 'Consentements') }
])

async function loadContacts() {
  registryLoading.value = true
  registryError.value = ''
  try {
    const res = await api.get('/travel/contacts', { params: { per_page: 100 }, _skipAuthRedirect: true })
    contacts.value = res.data?.data || []
  } catch (err) {
    const status = err.response?.status
    registryError.value = status === 404 || status === 405
      ? t('travel.contacts.registryUnavailable', 'Registre indisponible — endpoint de liste non encore livré sur ce déploiement.')
      : (err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.'))
  } finally {
    registryLoading.value = false
  }
}

async function toggleConsent(row, ch) {
  try {
    await api.patch(
      `/travel/contacts/${row.id}`,
      { channel: ch.key, consent: !row[`${ch.key}_consent_given`] },
      { _skipAuthRedirect: true }
    )
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    await loadContacts()
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.saveFailed', "Échec de l'enregistrement."))
>>>>>>> origin/bc/bc24-travel-admin-ui
  }
}

/* ── notification manuelle ─────────────────────────────────── */
<<<<<<< HEAD
function openNotify(row) {
  notifyTarget.value = row
  notifyMessage.value = ''
  notifyChannels.value = ['email']
=======
const notifyChannels = [
  { key: 'email', label: 'Email' },
  { key: 'app', label: t('travel.contacts.channelApp', 'Application (employé lié)') }
]

const notifyOpen = ref(false)
const notifyTarget = ref('')
const notifyRow = ref(null)
const notifyForm = ref({ message: '', channels: ['email'] })
const notifyErrors = ref({})
const notifyError = ref('')
const notifySaving = ref(false)

function openNotify(row) {
  notifyRow.value = row
  notifyTarget.value = [row.first_name, row.last_name].filter(Boolean).join(' ') || row.email || `#${row.id}`
  notifyForm.value = { message: '', channels: ['email'] }
  notifyErrors.value = {}
>>>>>>> origin/bc/bc24-travel-admin-ui
  notifyError.value = ''
  notifyOpen.value = true
}

function closeNotify() {
  notifyOpen.value = false
<<<<<<< HEAD
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
=======
  notifyRow.value = null
}

async function sendNotify() {
  if (!notifyRow.value) return
  notifySaving.value = true
  notifyErrors.value = {}
  notifyError.value = ''
  try {
    await api.post(
      `/travel/contacts/${notifyRow.value.id}/notify`,
      { message: notifyForm.value.message, channels: notifyForm.value.channels },
      { _skipAuthRedirect: true }
    )
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    notifyOpen.value = false
  } catch (err) {
    const data = err.response?.data || {}
    if (data.errors) {
      notifyErrors.value = Object.fromEntries(
        Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      )
    }
    notifyError.value = data.message || data.localized_message || t('travel.error.saveFailed', "Échec de l'enregistrement.")
  } finally {
    notifySaving.value = false
  }
}

onMounted(loadContacts)
>>>>>>> origin/bc/bc24-travel-admin-ui
</script>
