<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          {{ $t('travel.contacts.title', 'Contacts voyageurs') }}
        </h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('travel.contacts.subtitle', 'Registre des demandes et consentements par canal (opt-in / opt-out horodaté) + notification manuelle.') }}
        </p>
      </div>
      <button type="button" class="btn-primary" @click="openContactCreate">
        <PlusIcon class="mr-1 inline h-4 w-4" />
        {{ $t('travel.contacts.newRequest', 'Nouvelle demande') }}
      </button>
    </div>

    <DataTable
      :columns="contactColumns"
      :rows="contacts"
      :loading="loadingContacts"
      :error="errors.contacts"
      :search-keys="['first_name', 'last_name', 'email', 'phone']"
      :search-placeholder="$t('travel.contacts.search', 'Rechercher un contact…')"
      default-sort="id"
      :caption="$t('travel.contacts.listCaption', 'Demandes & consentements')"
      key-field="id"
    >
      <template #cell-name="{ row }">
        <span class="font-medium text-slate-900 dark:text-white">
          {{ [row.first_name, row.last_name].filter(Boolean).join(' ') || '—' }}
        </span>
      </template>
      <template #cell-consents="{ row }">
        <div class="flex flex-wrap gap-1">
          <StatusBadge
            :status="consentState(row.email_consent_given)"
            :map="consentMap"
          />
          <StatusBadge
            :status="consentState(row.sms_consent_given)"
            :map="consentMap"
          />
          <StatusBadge
            :status="consentState(row.whatsapp_consent_given)"
            :map="consentMap"
          />
        </div>
      </template>
      <template #row-actions="{ row }">
        <button type="button" class="btn-secondary" @click="openConsent(row)">
          {{ $t('travel.contacts.consents', 'Consentements') }}
        </button>
        <button type="button" class="btn-secondary" @click="openNotify(row)">
          {{ $t('travel.contacts.notify', 'Notifier') }}
        </button>
      </template>
    </DataTable>

    <!-- Modale nouvelle demande (TRAVEL-416/#6068 → POST /travel/contact) -->
    <TravelModal
      :open="contactFormOpen"
      :title="t('travel.contacts.newRequestTitle', 'Nouvelle demande de contact')"
      wide
      @close="closeContactForm"
    >
      <form class="grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="saveContact">
        <FormField
          id="travel-contact-first-name"
          :label="t('travel.field.firstName', 'Prénom')"
          :error="contactFormErrors.first_name"
        >
          <template #default="{ ariaInvalid, describedBy }">
            <input v-model="contactForm.first_name" type="text" maxlength="120" class="form-input" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" />
          </template>
        </FormField>
        <FormField
          id="travel-contact-last-name"
          :label="t('travel.field.lastName', 'Nom')"
          :error="contactFormErrors.last_name"
        >
          <template #default="{ ariaInvalid, describedBy }">
            <input v-model="contactForm.last_name" type="text" maxlength="120" class="form-input" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" />
          </template>
        </FormField>
        <FormField
          id="travel-contact-email"
          :label="t('travel.field.email', 'Email')"
          :error="contactFormErrors.email"
          required
        >
          <template #default="{ ariaInvalid, describedBy }">
            <input v-model="contactForm.email" type="email" maxlength="190" class="form-input" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" required />
          </template>
        </FormField>
        <FormField
          id="travel-contact-phone"
          :label="t('travel.field.phone', 'Téléphone')"
          :error="contactFormErrors.phone"
        >
          <template #default="{ ariaInvalid, describedBy }">
            <input v-model="contactForm.phone" type="tel" maxlength="40" class="form-input" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" />
          </template>
        </FormField>
        <FormField
          id="travel-contact-message"
          :label="t('travel.field.message', 'Message')"
          :error="contactFormErrors.message"
          required
        >
          <template #default="{ ariaInvalid, describedBy }">
            <textarea v-model="contactForm.message" rows="4" maxlength="2000" class="form-input" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" required></textarea>
          </template>
        </FormField>
        <div class="flex items-start gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-800">
          <input
            id="travel-contact-consent-email"
            v-model="contactForm.consent_email"
            type="checkbox"
            class="mt-0.5 h-4 w-4 shrink-0"
            :aria-invalid="consentEmailAriaInvalid"
          />
          <label class="text-sm text-slate-600 dark:text-slate-300" for="travel-contact-consent-email">
            {{ $t('travel.contacts.consentEmailLabel', "J'accepte d'être recontacté(e) par email (consentement obligatoire — RGPD).") }}
            <span class="text-red-500">*</span>
          </label>
        </div>
        <p v-if="contactFormErrors.consent_email" class="col-span-full text-sm text-red-600 dark:text-red-400">
          {{ contactFormErrors.consent_email }}
        </p>

        <p v-if="contactGlobalError" class="col-span-full rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-400" role="alert">
          {{ contactGlobalError }}
        </p>

        <div class="col-span-full flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="contactFormOpen = false">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="contactSaving">
            {{ contactSaving ? $t('common.busy', 'En cours…') : $t('common.save', 'Enregistrer') }}
          </button>
        </div>
      </form>
    </TravelModal>

    <!-- Modale consentements (TRAVEL-415/#6067 → POST /travel/contacts/{id}/consent) -->
    <TravelModal
      :open="consentOpen"
      :title="t('travel.contacts.consentsTitle', 'Consentements par canal')"
      @close="closeConsentModal"
    >
      <form class="grid grid-cols-1 gap-4" @submit.prevent="saveConsent">
        <p class="text-sm text-slate-600 dark:text-slate-300">
          {{ $t('travel.contacts.consentBody', "Opt-in / opt-out horodaté pour chaque canal. Un canal refusé ne recevra aucune notification.") }}
        </p>
        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300" for="travel-consent-email">
          <input id="travel-consent-email" v-model="consentForm.email_consent" type="checkbox" class="h-4 w-4" />
          {{ $t('travel.contacts.channelEmail', 'Email') }}
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300" for="travel-consent-sms">
          <input id="travel-consent-sms" v-model="consentForm.sms_consent" type="checkbox" class="h-4 w-4" />
          {{ $t('travel.contacts.channelSms', 'SMS') }}
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300" for="travel-consent-whatsapp">
          <input id="travel-consent-whatsapp" v-model="consentForm.whatsapp_consent" type="checkbox" class="h-4 w-4" />
          {{ $t('travel.contacts.channelWhatsapp', 'WhatsApp') }}
        </label>

        <p v-if="consentError" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-400" role="alert">
          {{ consentError }}
        </p>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="consentOpen = false">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="consentSaving">
            {{ consentSaving ? $t('common.busy', 'En cours…') : $t('common.save', 'Enregistrer') }}
          </button>
        </div>
      </form>
    </TravelModal>

    <!-- Modale notification manuelle (TRAVEL-910/#6113 → POST /travel/contacts/{id}/notify) -->
    <TravelModal
      :open="notifyOpen"
      :title="t('travel.contacts.notifyTitle', 'Notification manuelle')"
      @close="closeNotifyModal"
    >
      <form class="grid grid-cols-1 gap-4" @submit.prevent="saveNotify">
        <p class="text-sm text-slate-600 dark:text-slate-300">
          {{ $t('travel.contacts.notifyBody', 'Message borné (2000 caractères max). Seuls les canaux consentis sont proposés — 422 si aucun canal consenti.') }}
        </p>
        <FormField
          id="travel-notify-message"
          :label="t('travel.field.message', 'Message')"
          :error="notifyErrors.message"
          required
        >
          <template #default="{ ariaInvalid, describedBy }">
            <textarea v-model="notifyForm.message" rows="4" maxlength="2000" class="form-input" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" required></textarea>
          </template>
        </FormField>
        <fieldset class="space-y-2">
          <legend class="text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $t('travel.contacts.channels', 'Canaux (respect du consentement)') }}
          </legend>
          <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300" for="travel-notify-channel-email">
            <input id="travel-notify-channel-email" v-model="notifyForm.channels" type="checkbox" value="email" class="h-4 w-4" :disabled="!currentContact?.email_consent_given" />
            {{ $t('travel.contacts.channelEmail', 'Email') }}
            <span v-if="!currentContact?.email_consent_given" class="text-xs text-slate-400">({{ $t('travel.contacts.notConsented', 'non consenti') }})</span>
          </label>
          <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300" for="travel-notify-channel-app">
            <input id="travel-notify-channel-app" v-model="notifyForm.channels" type="checkbox" value="app" class="h-4 w-4" />
            {{ $t('travel.contacts.channelApp', 'Application / push') }}
          </label>
        </fieldset>

        <p v-if="notifyGlobalError" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-400" role="alert">
          {{ notifyGlobalError }}
        </p>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="notifyOpen = false">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="notifySaving">
            {{ notifySaving ? $t('common.busy', 'En cours…') : $t('common.send', 'Envoyer') }}
          </button>
        </div>
      </form>
    </TravelModal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { PlusIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import FormField from '@/components/common/FormField.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import TravelModal from '@/components/travel/TravelModal.vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const toast = useToast()

const contacts = ref([])
const loadingContacts = ref(false)
const errors = ref({ contacts: '' })

const contactColumns = computed(() => [
  { key: 'name', label: t('travel.field.name', 'Nom') },
  { key: 'email', label: t('travel.field.email', 'Email'), sortable: true },
  { key: 'phone', label: t('travel.field.phone', 'Téléphone'), sortable: true },
  { key: 'consents', label: t('travel.contacts.consents', 'Consentements') },
  { key: 'created_at', label: t('travel.contacts.createdAt', 'Reçu le'), sortable: true }
])

const consentMap = {
  consented: { labelKey: 'travel.contacts.consented', color: 'green' },
  refused: { labelKey: 'travel.contacts.refused', color: 'gray' }
}

function consentState(given) {
  return given ? 'consented' : 'refused'
}

const consentEmailAriaInvalid = computed(() =>
  contactFormErrors.value.consent_email ? 'true' : undefined
)

function closeContactForm() {
  contactFormOpen.value = false
}

function closeConsentModal() {
  consentOpen.value = false
}

function closeNotifyModal() {
  notifyOpen.value = false
}

async function loadContacts() {
  loadingContacts.value = true
  errors.value.contacts = ''
  try {
    const res = await api.get('/travel/contacts', { params: { per_page: 100 }, _skipAuthRedirect: true })
    contacts.value = res.data?.data || []
  } catch (err) {
    errors.value.contacts = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    loadingContacts.value = false
  }
}

/* ── nouvelle demande (POST /travel/contact) ───────────────── */
const contactFormOpen = ref(false)
const contactSaving = ref(false)
const contactForm = ref({})
const contactFormErrors = ref({})
const contactGlobalError = ref('')

function openContactCreate() {
  contactForm.value = { first_name: '', last_name: '', email: '', phone: '', message: '', consent_email: false }
  contactFormErrors.value = {}
  contactGlobalError.value = ''
  contactFormOpen.value = true
}

async function saveContact() {
  contactSaving.value = true
  contactGlobalError.value = ''
  contactFormErrors.value = {}
  try {
    const payload = {
      first_name: contactForm.value.first_name || null,
      last_name: contactForm.value.last_name || null,
      email: contactForm.value.email,
      phone: contactForm.value.phone || null,
      message: contactForm.value.message,
      consent_email: contactForm.value.consent_email
    }
    await api.post('/travel/contact', payload, { _skipAuthRedirect: true })
    toast.success(t('travel.contacts.submitted', 'Demande enregistrée — accusé envoyé.'))
    contactFormOpen.value = false
    await loadContacts()
  } catch (err) {
    const data = err.response?.data || {}
    if (data.errors) {
      contactFormErrors.value = Object.fromEntries(
        Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      )
    }
    contactGlobalError.value = data.message || data.localized_message || t('travel.error.saveFailed', "Échec de l'enregistrement.")
  } finally {
    contactSaving.value = false
  }
}

/* ── consentements (POST /travel/contacts/{id}/consent) ────── */
const consentOpen = ref(false)
const consentSaving = ref(false)
const consentForm = ref({})
const consentError = ref('')
const currentContact = ref(null)

function openConsent(row) {
  currentContact.value = row
  consentForm.value = {
    email_consent: Boolean(row.email_consent_given),
    sms_consent: Boolean(row.sms_consent_given),
    whatsapp_consent: Boolean(row.whatsapp_consent_given)
  }
  consentError.value = ''
  consentOpen.value = true
}

async function saveConsent() {
  if (!currentContact.value) return
  consentSaving.value = true
  consentError.value = ''
  try {
    await api.post(`/travel/contacts/${currentContact.value.id}/consent`, consentForm.value, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    consentOpen.value = false
    await loadContacts()
  } catch (err) {
    consentError.value = err.response?.data?.message || t('travel.error.saveFailed', "Échec de l'enregistrement.")
  } finally {
    consentSaving.value = false
  }
}

/* ── notification manuelle (POST /travel/contacts/{id}/notify) ─ */
const notifyOpen = ref(false)
const notifySaving = ref(false)
const notifyForm = ref({})
const notifyErrors = ref({})
const notifyGlobalError = ref('')

function openNotify(row) {
  currentContact.value = row
  notifyForm.value = { message: '', channels: [] }
  notifyErrors.value = {}
  notifyGlobalError.value = ''
  notifyOpen.value = true
}

async function saveNotify() {
  if (!currentContact.value) return
  notifySaving.value = true
  notifyGlobalError.value = ''
  notifyErrors.value = {}
  try {
    const payload = {
      message: notifyForm.value.message,
      channels: notifyForm.value.channels.length ? notifyForm.value.channels : undefined
    }
    await api.post(`/travel/contacts/${currentContact.value.id}/notify`, payload, { _skipAuthRedirect: true })
    toast.success(t('travel.contacts.notified', 'Notification envoyée.'))
    notifyOpen.value = false
  } catch (err) {
    const status = err.response?.status
    const data = err.response?.data || {}
    if (status === 422 && data.message) {
      // « Aucun canal consenti » → explicite pour l'admin
      notifyGlobalError.value = data.message
    } else if (data.errors) {
      notifyErrors.value = Object.fromEntries(
        Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      )
    } else {
      notifyGlobalError.value = data.message || t('travel.error.actionFailed', "L'action a échoué.")
    }
  } finally {
    notifySaving.value = false
  }
}

onMounted(loadContacts)
</script>
