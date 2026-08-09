<template>
  <div class="space-y-8 animate-fade-in max-w-3xl">
    <!-- En-tete -->
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
        {{ $t('marketing.oauth.title') }}
      </h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        {{ $t('marketing.oauth.subtitle') }}
      </p>
    </div>

    <!-- Bandeau d information Ayrshare -->
    <div
      class="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-700 dark:bg-sky-900/20"
    >
      <InformationCircleIcon class="mt-0.5 h-5 w-5 flex-shrink-0 text-sky-500" />
      <p class="text-sm text-sky-700 dark:text-sky-300">
        {{ $t('marketing.oauth.ayrshare_info') }}
      </p>
    </div>

    <!-- LinkedIn -->
    <OAuthProviderCard
      provider="linkedin"
      :label="$t('marketing.oauth.providers.linkedin.label')"
      :description="$t('marketing.oauth.providers.linkedin.description')"
      icon-classes="text-blue-700"
      :loading="saving.linkedin"
      :form="forms.linkedin"
      :label-client-id="$t('marketing.oauth.fields.client_id')"
      :label-client-secret="$t('marketing.oauth.fields.client_secret')"
      :label-redirect-uri="$t('marketing.oauth.fields.redirect_uri')"
      :label-secret-hint="$t('marketing.oauth.fields.secret_hint')"
      :placeholder-id="$t('marketing.oauth.fields.placeholder_id')"
      :placeholder-secret="$t('marketing.oauth.fields.placeholder_secret')"
      :placeholder-uri="$t('marketing.oauth.fields.placeholder_uri')"
      :label-save="$t('marketing.oauth.save')"
      @save="saveProvider('linkedin')"
    />

    <!-- Facebook / Meta -->
    <OAuthProviderCard
      provider="facebook"
      :label="$t('marketing.oauth.providers.facebook.label')"
      :description="$t('marketing.oauth.providers.facebook.description')"
      icon-classes="text-blue-600"
      :loading="saving.facebook"
      :form="forms.facebook"
      :label-client-id="$t('marketing.oauth.fields.client_id')"
      :label-client-secret="$t('marketing.oauth.fields.client_secret')"
      :label-redirect-uri="$t('marketing.oauth.fields.redirect_uri')"
      :label-secret-hint="$t('marketing.oauth.fields.secret_hint')"
      :placeholder-id="$t('marketing.oauth.fields.placeholder_id')"
      :placeholder-secret="$t('marketing.oauth.fields.placeholder_secret')"
      :placeholder-uri="$t('marketing.oauth.fields.placeholder_uri')"
      :label-save="$t('marketing.oauth.save')"
      @save="saveProvider('facebook')"
    />

    <!-- X (Twitter) -->
    <OAuthProviderCard
      provider="twitter"
      :label="$t('marketing.oauth.providers.twitter.label')"
      :description="$t('marketing.oauth.providers.twitter.description')"
      icon-classes="text-slate-900 dark:text-white"
      :loading="saving.twitter"
      :form="forms.twitter"
      :label-client-id="$t('marketing.oauth.fields.client_id')"
      :label-client-secret="$t('marketing.oauth.fields.client_secret')"
      :label-redirect-uri="$t('marketing.oauth.fields.redirect_uri')"
      :label-secret-hint="$t('marketing.oauth.fields.secret_hint')"
      :placeholder-id="$t('marketing.oauth.fields.placeholder_id')"
      :placeholder-secret="$t('marketing.oauth.fields.placeholder_secret')"
      :placeholder-uri="$t('marketing.oauth.fields.placeholder_uri')"
      :label-save="$t('marketing.oauth.save')"
      @save="saveProvider('twitter')"
    />
  </div>
</template>

<script setup>
import { reactive } from 'vue'
import { InformationCircleIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
/** Traduction avec fallback sur la clé elle-même pour faciliter le débogage */
const t = (key, vars = {}) => {
  let msg = translate(localeStore.current, key) || key
  // Interpolation simple : {{ provider }} → valeur de vars.provider
  for (const [k, v] of Object.entries(vars)) {
    msg = msg.replace(`{{ ${k} }}`, String(v))
  }
  return msg
}
const toast = useToast()

const forms = reactive({
  linkedin: { clientId: '', clientSecret: '', redirectUri: '' },
  facebook: { clientId: '', clientSecret: '', redirectUri: '' },
  twitter:  { clientId: '', clientSecret: '', redirectUri: '' },
})

const saving = reactive({ linkedin: false, facebook: false, twitter: false })

async function saveProvider(provider) {
  saving[provider] = true
  try {
    const form = forms[provider]
    const payload = { provider, client_id: form.clientId, redirect_uri: form.redirectUri }
    if (form.clientSecret.trim()) {
      payload.client_secret = form.clientSecret
    }
    await api.put('/v1/platform/marketing/oauth-config', payload)
    toast.success(t('marketing.oauth.saved_ok', { provider }))
    forms[provider].clientSecret = ''
  } catch {
    // errors handled by global api.js interceptor
  } finally {
    saving[provider] = false
  }
}
</script>

<script>
export const OAuthProviderCard = {
  name: 'OAuthProviderCard',
  props: {
    provider:           { type: String, required: true },
    label:              { type: String, required: true },
    description:        { type: String, required: true },
    iconClasses:        { type: String, default: '' },
    loading:            { type: Boolean, default: false },
    form:               { type: Object, required: true },
    labelClientId:      { type: String, default: '' },
    labelClientSecret:  { type: String, default: '' },
    labelRedirectUri:   { type: String, default: '' },
    labelSecretHint:    { type: String, default: '' },
    placeholderId:      { type: String, default: '' },
    placeholderSecret:  { type: String, default: '' },
    placeholderUri:     { type: String, default: '' },
    labelSave:          { type: String, default: '' },
  },
  emits: ['save'],
  methods: {
    fieldId(provider, field) { return provider + '-' + field },
  },
  template: `
    <div class="card animate-slide-up">
      <div class="card-header flex items-center gap-3">
        <span :class="['text-2xl font-black', iconClasses]">{{ providerInitial }}</span>
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ label }}</h2>
          <p class="mt-0.5 text-sm font-medium text-slate-500 dark:text-slate-400">{{ description }}</p>
        </div>
      </div>
      <form class="card-body space-y-5" @submit.prevent="$emit('save')">
        <div>
          <label :for="fieldId(provider, 'client-id')" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ labelClientId }}</label>
          <input :id="fieldId(provider, 'client-id')" v-model="form.clientId" type="text" class="form-input" autocomplete="off" :placeholder="placeholderId" />
        </div>
        <div>
          <label :for="fieldId(provider, 'client-secret')" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            {{ labelClientSecret }}
            <span class="ml-1 text-xs font-normal text-slate-400">{{ labelSecretHint }}</span>
          </label>
          <input :id="fieldId(provider, 'client-secret')" v-model="form.clientSecret" type="password" class="form-input" autocomplete="new-password" :placeholder="placeholderSecret" />
        </div>
        <div>
          <label :for="fieldId(provider, 'redirect-uri')" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ labelRedirectUri }}</label>
          <input :id="fieldId(provider, 'redirect-uri')" v-model="form.redirectUri" type="url" class="form-input" :placeholder="placeholderUri" />
        </div>
        <div class="flex justify-end">
          <button type="submit" class="btn-primary" :disabled="loading">
            <span v-if="loading" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent inline-block"></span>
            {{ labelSave }}
          </button>
        </div>
      </form>
    </div>
  `,
  computed: {
    providerInitial() { return this.label.charAt(0).toUpperCase() },
  },
}

export default { components: { OAuthProviderCard } }
</script>
