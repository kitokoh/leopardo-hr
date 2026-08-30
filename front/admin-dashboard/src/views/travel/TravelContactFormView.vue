<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <div>
      <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-white">
        {{ $t('travel.contactForm.title', 'Nous contacter') }}
      </h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400">
        {{ $t('travel.contactForm.subtitle', 'Envoyez une demande à l\u0027agence : un conseiller vous répondra.') }}
      </p>
    </div>

    <div
      v-if="submitted"
      class="rounded-2xl border border-green-200 bg-green-50 px-6 py-8 text-center dark:border-green-800 dark:bg-green-900/20"
      role="status"
    >
      <CheckCircleIcon class="mx-auto h-12 w-12 text-green-500" />
      <h2 class="mt-3 text-xl font-bold text-slate-900 dark:text-white">
        {{ $t('travel.contactForm.ackTitle', 'Demande bien reçue') }}
      </h2>
      <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
        {{ $t('travel.contactForm.ackBody', 'Merci, votre demande a bien été transmise à notre agence. Nous reviendrons vers vous rapidement.') }}
      </p>
      <button class="btn-primary mt-6" type="button" @click="reset">
        {{ $t('travel.contactForm.newRequest', 'Nouvelle demande') }}
      </button>
    </div>

    <form v-else class="space-y-5 rounded-2xl glass-card p-6" @submit.prevent="submit">
      <div v-if="formError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
        {{ formError }}
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="contact-first-name">
            {{ $t('travel.contactForm.firstName', 'Prénom') }}
          </label>
          <input
            id="contact-first-name"
            v-model="form.first_name"
            class="form-input mt-1"
            type="text"
            maxlength="120"
            :placeholder="$t('travel.contactForm.firstNamePlaceholder', 'Votre prénom')"
          />
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="contact-last-name">
            {{ $t('travel.contactForm.lastName', 'Nom') }}
          </label>
          <input
            id="contact-last-name"
            v-model="form.last_name"
            class="form-input mt-1"
            type="text"
            maxlength="120"
            :placeholder="$t('travel.contactForm.lastNamePlaceholder', 'Votre nom')"
          />
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="contact-email">
          {{ $t('travel.contactForm.email', 'Email') }} *
        </label>
        <input
          id="contact-email"
          v-model="form.email"
          class="form-input mt-1"
          type="email"
          maxlength="190"
          required
          :placeholder="$t('travel.contactForm.emailPlaceholder', 'vous@exemple.com')"
        />
        <p v-if="fieldErrors.email" class="mt-1 text-xs text-red-600">{{ fieldErrors.email }}</p>
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="contact-phone">
          {{ $t('travel.contactForm.phone', 'Téléphone') }}
        </label>
        <input
          id="contact-phone"
          v-model="form.phone"
          class="form-input mt-1"
          type="tel"
          maxlength="40"
          :placeholder="$t('travel.contactForm.phonePlaceholder', 'Votre téléphone')"
        />
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="contact-message">
          {{ $t('travel.contactForm.message', 'Message') }} *
        </label>
        <textarea
          id="contact-message"
          v-model="form.message"
          class="form-input mt-1"
          rows="5"
          maxlength="2000"
          required
          :placeholder="$t('travel.contactForm.messagePlaceholder', 'Votre demande (2 000 caractères max)…')"
        ></textarea>
        <p v-if="fieldErrors.message" class="mt-1 text-xs text-red-600">{{ fieldErrors.message }}</p>
      </div>

      <label class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
        <input v-model="form.consent_email" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600" type="checkbox" required />
        <span>
          {{ $t('travel.contactForm.consent', "J'accepte d'être contacté(e) par email au sujet de ma demande.") }}
        </span>
      </label>
      <p v-if="fieldErrors.consent_email" class="text-xs text-red-600">{{ fieldErrors.consent_email }}</p>

      <div class="flex justify-end gap-2">
        <button class="btn-secondary" type="button" @click="reset">
          {{ $t('common.cancel', 'Annuler') }}
        </button>
        <button class="btn-primary" type="submit" :disabled="sending">
          {{ sending ? $t('common.busy', 'Envoi en cours…') : $t('travel.contactForm.submit', 'Envoyer la demande') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { CheckCircleIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

/**
 * TRAVEL-912 (#6417) — Formulaire de contact (surface actuelle : utilisateur
 * authentifié du tenant). Consomme exclusivement POST /travel/contact
 * (validation bornes + consentement obligatoire côté serveur).
 */
const form = reactive({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  message: '',
  consent_email: false
})

const fieldErrors = ref({})
const formError = ref('')
const sending = ref(false)
const submitted = ref(false)

async function submit() {
  sending.value = true
  formError.value = ''
  fieldErrors.value = {}
  try {
    await api.post('/travel/contact', {
      first_name: form.first_name || null,
      last_name: form.last_name || null,
      email: form.email,
      phone: form.phone || null,
      message: form.message,
      consent_email: form.consent_email
    }, { _skipAuthRedirect: true })
    submitted.value = true
  } catch (err) {
    const data = err.response?.data || {}
    if (data.errors) {
      fieldErrors.value = Object.fromEntries(
        Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      )
    }
    formError.value = data.message || t('travel.error.saveFailed', "Échec de l'envoi.")
  } finally {
    sending.value = false
  }
}

function reset() {
  form.first_name = ''
  form.last_name = ''
  form.email = ''
  form.phone = ''
  form.message = ''
  form.consent_email = false
  fieldErrors.value = {}
  formError.value = ''
  submitted.value = false
}
</script>
