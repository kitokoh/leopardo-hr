<template>
  <div class="space-y-8 animate-fade-in max-w-3xl">
    <!-- En-tête -->
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
        Marketing — OAuth Réseaux Sociaux
      </h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        Configurez les credentials OAuth (Client ID / Client Secret) utilisés par le module
        Marketing pour publier sur LinkedIn, Facebook/Meta et X (Twitter).
      </p>
    </div>

    <!-- Bandeau d'information Ayrshare -->
    <div
      class="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-700 dark:bg-sky-900/20"
    >
      <InformationCircleIcon class="mt-0.5 h-5 w-5 flex-shrink-0 text-sky-500" />
      <p class="text-sm text-sky-700 dark:text-sky-300">
        Les publications sont routées via l'agrégateur
        <strong>Ayrshare</strong>. Les credentials ci-dessous sont stockés de façon sécurisée et
        utilisés pour initialiser la connexion OAuth de chaque réseau social dans Ayrshare.
      </p>
    </div>

    <!-- LinkedIn -->
    <OAuthProviderCard
      provider="linkedin"
      label="LinkedIn"
      description="Connexion aux pages entreprises et profils LinkedIn via l'API Marketing v2."
      :icon-classes="'text-[#0A66C2]'"
      :loading="saving.linkedin"
      :form="forms.linkedin"
      @save="saveProvider('linkedin')"
    />

    <!-- Facebook / Meta -->
    <OAuthProviderCard
      provider="facebook"
      label="Facebook / Meta"
      description="Connexion aux pages Facebook et comptes Instagram Business via l'API Graph."
      :icon-classes="'text-[#1877F2]'"
      :loading="saving.facebook"
      :form="forms.facebook"
      @save="saveProvider('facebook')"
    />

    <!-- X (Twitter) -->
    <OAuthProviderCard
      provider="twitter"
      label="X (Twitter)"
      description="Connexion via l'API Twitter v2 / OAuth 2.0 PKCE."
      :icon-classes="'text-slate-900 dark:text-white'"
      :loading="saving.twitter"
      :form="forms.twitter"
      @save="saveProvider('twitter')"
    />
  </div>
</template>

<script setup>
import { reactive } from 'vue'
import { InformationCircleIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

// ─── Toast ───────────────────────────────────────────────────────────────────
const toast = useToast()

// ─── État des formulaires ─────────────────────────────────────────────────────
/**
 * Chaque formulaire expose les champs OAuth nécessaires.
 * Les secrets sont masqués au chargement (l'API ne renvoie jamais le secret en clair) ;
 * l'utilisateur laisse le champ vide pour conserver la valeur existante.
 */
const forms = reactive({
  linkedin: { clientId: '', clientSecret: '', redirectUri: '' },
  facebook: { clientId: '', clientSecret: '', redirectUri: '' },
  twitter:  { clientId: '', clientSecret: '', redirectUri: '' },
})

const saving = reactive({ linkedin: false, facebook: false, twitter: false })

// ─── Persistance ──────────────────────────────────────────────────────────────
/**
 * Envoie les credentials au endpoint platform d'administration.
 * Le champ `client_secret` est omis s'il est vide pour ne pas écraser un secret existant.
 */
async function saveProvider(provider) {
  saving[provider] = true
  try {
    const form = forms[provider]
    const payload = { provider, client_id: form.clientId, redirect_uri: form.redirectUri }
    if (form.clientSecret.trim()) {
      payload.client_secret = form.clientSecret
    }
    await api.put('/v1/platform/marketing/oauth-config', payload)
    toast.success(`Configuration ${providerLabel(provider)} enregistrée.`)
    // Vider le champ secret après sauvegarde (bonne pratique sécurité)
    forms[provider].clientSecret = ''
  } catch {
    // Les erreurs sont déjà affichées par l'intercepteur global de api.js
  } finally {
    saving[provider] = false
  }
}

function providerLabel(provider) {
  return { linkedin: 'LinkedIn', facebook: 'Facebook/Meta', twitter: 'X (Twitter)' }[provider] ?? provider
}
</script>

<!-- ─── Composant interne : carte par fournisseur OAuth ─────────────────────── -->
<script>
/**
 * OAuthProviderCard — carte de configuration OAuth réutilisable pour chaque réseau social.
 * Encapsulée dans le même fichier pour garder la vue auto-contenue (pas de fichier séparé
 * nécessaire, composant non partagé en dehors de cette vue).
 */
export const OAuthProviderCard = {
  name: 'OAuthProviderCard',
  props: {
    provider:    { type: String, required: true },
    label:       { type: String, required: true },
    description: { type: String, required: true },
    iconClasses: { type: String, default: '' },
    loading:     { type: Boolean, default: false },
    form:        { type: Object, required: true },
  },
  emits: ['save'],
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
          <label
            :for="provider + '-client-id'"
            class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5"
          >
            Client ID
          </label>
          <input
            :id="provider + '-client-id'"
            v-model="form.clientId"
            type="text"
            class="form-input"
            autocomplete="off"
            placeholder="Laissez vide pour conserver la valeur actuelle"
          />
        </div>
        <div>
          <label
            :for="provider + '-client-secret'"
            class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5"
          >
            Client Secret
            <span class="ml-1 text-xs font-normal text-slate-400">(non affiché)</span>
          </label>
          <input
            :id="provider + '-client-secret'"
            v-model="form.clientSecret"
            type="password"
            class="form-input"
            autocomplete="new-password"
            placeholder="••••••••  Laissez vide pour ne pas modifier"
          />
        </div>
        <div>
          <label
            :for="provider + '-redirect-uri'"
            class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5"
          >
            Redirect URI
          </label>
          <input
            :id="provider + '-redirect-uri'"
            v-model="form.redirectUri"
            type="url"
            class="form-input"
            placeholder="https://votre-domaine.com/oauth/callback"
          />
        </div>
        <div class="flex justify-end">
          <button type="submit" class="btn-primary" :disabled="loading">
            <svg
              v-if="loading"
              class="mr-2 h-4 w-4 animate-spin"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
            Enregistrer
          </button>
        </div>
      </form>
    </div>
  `,
  computed: {
    providerInitial() {
      return this.label.charAt(0).toUpperCase()
    },
  },
}

export default { components: { OAuthProviderCard } }
</script>
