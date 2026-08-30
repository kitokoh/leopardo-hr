<template>
  <div class="space-y-6">
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
          <input v-model.trim="contactForm.first_name" type="text" class="form-input" maxlength="120" />
        </FormField>
        <FormField
          :id="'travel-contact-last'"
          :label="t('travel.contacts.field.lastName', 'Nom')"
          :error="contactErrors.last_name"
        >
          <input v-model.trim="contactForm.last_name" type="text" class="form-input" maxlength="120" />
        </FormField>
        <FormField
          :id="'travel-contact-email'"
          :label="t('travel.contacts.field.email', 'Email')"
          :error="contactErrors.email"
          required
        >
          <input v-model.trim="contactForm.email" type="email" class="form-input" required maxlength="190" />
        </FormField>
        <FormField
          :id="'travel-contact-phone'"
          :label="t('travel.contacts.field.phone', 'Téléphone')"
          :error="contactErrors.phone"
        >
          <input v-model.trim="contactForm.phone" type="tel" class="form-input" maxlength="40" />
        </FormField>
        <FormField
          :id="'travel-contact-message'"
          :label="t('travel.contacts.field.message', 'Message')"
          :error="contactErrors.message"
          class="col-span-full"
          required
        >
          <textarea v-model.trim="contactForm.message" class="form-input" rows="4" required maxlength="2000"></textarea>
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
          <textarea v-model.trim="notifyForm.message" class="form-input" rows="4" required maxlength="2000"></textarea>
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
import FormField from '@/components/common/FormField.vue'
import TravelModal from '@/components/travel/TravelModal.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const toast = useToast()

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
  }
}

/* ── notification manuelle ─────────────────────────────────── */
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
  notifyError.value = ''
  notifyOpen.value = true
}

function closeNotify() {
  notifyOpen.value = false
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
</script>
