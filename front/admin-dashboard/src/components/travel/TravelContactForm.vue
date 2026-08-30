<template>
  <div class="rounded-2xl glass-card p-5">
    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
      {{ t('travel.contacts.formTitle', 'Nous contacter') }}
    </h3>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
      {{ t('travel.contacts.formSubtitle', 'Une demande ? Laissez vos coordonnées — consentement requis pour être recontacté.') }}
    </p>

    <form class="mt-4 space-y-3" @submit.prevent="submit">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <input
          v-model="form.first_name"
          type="text"
          class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
          :placeholder="t('travel.contacts.firstName', 'Prénom')"
        />
        <input
          v-model="form.last_name"
          type="text"
          class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
          :placeholder="t('travel.contacts.lastName', 'Nom')"
        />
      </div>
      <input
        v-model="form.email"
        type="email"
        required
        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
        :placeholder="t('travel.contacts.email', 'Email')"
      />
      <input
        v-model="form.phone"
        type="tel"
        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
        :placeholder="t('travel.contacts.phone', 'Téléphone (optionnel)')"
      />
      <textarea
        v-model="form.message"
        required
        rows="3"
        maxlength="2000"
        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
        :placeholder="t('travel.contacts.message', 'Votre message')"
      ></textarea>
      <label class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
        <input v-model="form.consent" type="checkbox" required class="mt-0.5" />
        {{ t('travel.contacts.consentLabel', 'J’accepte d’être recontacté par email (consentement RGPD).') }}
      </label>
      <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
      <p v-if="success" class="text-sm text-emerald-600 dark:text-emerald-400">
        {{ t('travel.contacts.success', 'Demande envoyée — accusé de réception transmis.') }}
      </p>
      <button type="submit" class="btn-primary" :disabled="busy">
        {{ busy ? t('travel.contacts.sending', 'Envoi…') : t('travel.contacts.send', 'Envoyer') }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { createTravel } from '@/services/travel'

const { t } = useI18n()

const form = reactive({ first_name: '', last_name: '', email: '', phone: '', message: '', consent: false })
const busy = ref(false)
const error = ref('')
const success = ref(false)

async function submit() {
  busy.value = true
  error.value = ''
  success.value = false
  try {
    await createTravel('contact', {
      first_name: form.first_name,
      last_name: form.last_name,
      email: form.email,
      phone: form.phone,
      message: form.message,
      consent: true,
    })
    success.value = true
    form.first_name = ''
    form.last_name = ''
    form.email = ''
    form.phone = ''
    form.message = ''
    form.consent = false
  } catch (err) {
    error.value = err?.response?.data?.message || String(err)
  } finally {
    busy.value = false
  }
}
</script>
