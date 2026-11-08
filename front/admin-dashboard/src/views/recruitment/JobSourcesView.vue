<template>
  <div class="p-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">
          {{ t('recruitment.sources.title', 'Sources d’offres d’emploi') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400">
          {{ t('recruitment.sources.subtitle', 'Connectez des agrégateurs pour alimenter le portail public d’offres.') }}
        </p>
      </div>
      <button class="btn-secondary" :disabled="loading" @click="load()">
        {{ t('recruitment.common.refresh', 'Rafraîchir') }}
      </button>
    </div>

    <div
      v-if="error"
      class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-900/30 dark:bg-red-950/20 dark:text-red-400"
    >
      {{ error === 'forbidden'
        ? t('recruitment.common.forbidden', 'Vous n’avez pas la permission recruitment.view.')
        : t('recruitment.common.loadError', 'Impossible de charger les données.') }}
      <button v-if="error !== 'forbidden'" class="ml-3 underline" @click="load()">
        {{ t('recruitment.common.retry', 'Réessayer') }}
      </button>
    </div>

    <div v-if="loading" class="p-12 text-center text-sm text-slate-500">
      {{ t('recruitment.common.loading', 'Chargement…') }}
    </div>

    <div v-else class="grid gap-4 md:grid-cols-2">
      <div v-for="source in data" :key="source.provider" class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">
              {{ source.label || source.provider }}
            </h2>
            <p class="mt-1 text-xs text-slate-500">
              {{ source.offers_imported }} {{ t('recruitment.sources.offers', 'offres importées') }}
            </p>
          </div>
          <StatusBadge :status="source.status" :map="statusMap" />
        </div>

        <dl class="mt-4 space-y-1 text-xs text-slate-500">
          <div class="flex justify-between gap-3">
            <dt>{{ t('recruitment.sources.credentials', 'Identifiants') }}</dt>
            <dd class="font-semibold text-slate-700 dark:text-slate-300">
              {{ source.has_credentials
                ? t('recruitment.sources.credentialsSet', 'Enregistrés')
                : t('recruitment.sources.credentialsMissing', 'Aucun') }}
            </dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt>{{ t('recruitment.sources.lastSync', 'Dernière synchro') }}</dt>
            <dd class="font-semibold text-slate-700 dark:text-slate-300">
              {{ formatDate(source.last_synced_at) }}
            </dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt>{{ t('recruitment.sources.lastError', 'Dernière erreur') }}</dt>
            <dd class="max-w-[60%] truncate text-right font-semibold text-slate-700 dark:text-slate-300">
              {{ source.last_error || '—' }}
            </dd>
          </div>
        </dl>

        <!--
          Le formulaire n'affiche JAMAIS un identifiant existant : le secret ne
          quitte pas le serveur. On saisit un jeu complet, qui REMPLACE le
          précédent — un mélange partiel produirait un connecteur qui « a l'air »
          configuré mais dont la clé vient d'une autre organisation.
        -->
        <div v-if="editing === source.provider" class="mt-4 space-y-2">
          <div v-for="key in source.credential_keys" :key="key">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400">{{ key }}</label>
            <input
              v-model="draft[key]"
              type="password"
              autocomplete="new-password"
              class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
            />
          </div>
          <div class="flex gap-2 pt-1">
            <RowActionButton
              :label="t('recruitment.sources.save', 'Enregistrer')"
              variant="primary"
              :disabled="saving === source.provider"
              @click="save(source)"
            />
            <RowActionButton
              :label="t('recruitment.common.cancel', 'Annuler')"
              variant="secondary"
              @click="editing = null"
            />
          </div>
        </div>

        <div v-else class="mt-4 flex flex-wrap gap-2">
          <RowActionButton
            :label="t('recruitment.sources.configure', 'Configurer')"
            variant="secondary"
            @click="startEditing(source)"
          />
          <RowActionButton
            :label="t('recruitment.sources.test', 'Tester')"
            variant="secondary"
            :disabled="testing === source.provider"
            @click="test(source)"
          />
          <RowActionButton
            :label="t('recruitment.sources.sync', 'Synchroniser')"
            variant="primary"
            :disabled="syncing === source.provider || !source.has_credentials"
            @click="sync(source)"
          />
          <RowActionButton
            v-if="source.has_credentials"
            :label="t('recruitment.sources.revoke', 'Révoquer')"
            variant="danger"
            @click="revoke(source)"
          />
        </div>

        <p v-if="feedback[source.provider]" class="mt-2 text-xs font-semibold text-slate-600 dark:text-slate-400">
          {{ feedback[source.provider] }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import api from '@/services/api'
import StatusBadge from '@/components/common/StatusBadge.vue'
import RowActionButton from '@/components/common/RowActionButton.vue'
import { useSearchIntelList, formatDate } from '@/composables/useSearchIntelList'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

/**
 * BC-10 (#9100) — connecteurs d'agrégateurs d'offres d'emploi.
 *
 * Trois partis pris visibles dans cette vue :
 *
 * 1. **L'état est affiché honnêtement.** `not_connected` est un état de premier
 *    rang, pas un zéro honteux : un connecteur jamais configuré se lit
 *    « Non connecté », pas « 0 offre ».
 * 2. **Le test ne ment pas.** Le message de retour reprend celui du serveur, qui
 *    dit explicitement qu'un test local ne prouve pas l'accès fournisseur. On
 *    n'affiche donc jamais « Connexion vérifiée » après un simple test.
 * 3. **Aucun secret n'est pré-rempli.** Le champ est vide et de type password :
 *    le serveur ne renvoie jamais la valeur stockée.
 */
const { data, loading, error, load } = useSearchIntelList('/recruitment/sources')

const editing = ref(null)
const saving = ref(null)
const testing = ref(null)
const syncing = ref(null)
const draft = reactive({})
const feedback = reactive({})

const statusMap = {
  connected: { label: t('recruitment.sourceStatus.connected', 'Connecté'), color: 'green' },
  not_connected: { label: t('recruitment.sourceStatus.notConnected', 'Non connecté'), color: 'gray' },
  error: { label: t('recruitment.sourceStatus.error', 'En erreur'), color: 'red' },
}

function startEditing(source) {
  editing.value = source.provider
  feedback[source.provider] = ''
  // Champs vides : on ne pré-remplit jamais un secret.
  for (const key of source.credential_keys ?? []) {
    draft[key] = ''
  }
}

async function save(source) {
  saving.value = source.provider
  feedback[source.provider] = ''
  try {
    const credentials = {}
    for (const key of source.credential_keys ?? []) {
      if (draft[key]) credentials[key] = draft[key]
    }
    await api.put(`/platform/recruitment/sources/${source.provider}`, { credentials }, { _skipToast: true })
    feedback[source.provider] = t('recruitment.sources.saved', 'Identifiants enregistrés. Lancez une synchronisation pour valider l’accès.')
    editing.value = null
  } catch (e) {
    feedback[source.provider] = e?.response?.data?.message
      || t('recruitment.sources.saveFailed', 'Enregistrement refusé.')
  } finally {
    saving.value = null
    await load()
  }
}

async function test(source) {
  testing.value = source.provider
  feedback[source.provider] = ''
  try {
    const response = await api.post(`/platform/recruitment/sources/${source.provider}/test`, {}, { _skipToast: true })
    // Le serveur dit si l'accès fournisseur a RÉELLEMENT été vérifié : on
    // relaie son verdict au lieu de le réinterpréter.
    const verified = response.data?.result?.provider_access_verified ?? false
    feedback[source.provider] = verified
      ? t('recruitment.sources.testVerified', 'Accès fournisseur vérifié.')
      : (response.data?.result?.message
        || t('recruitment.sources.testPending', 'Identifiants complets : lancez une synchronisation pour valider l’accès.'))
  } catch (e) {
    feedback[source.provider] = e?.response?.data?.message
      || t('recruitment.sources.testFailed', 'Le test a échoué.')
  } finally {
    testing.value = null
    await load()
  }
}

async function sync(source) {
  syncing.value = source.provider
  feedback[source.provider] = ''
  try {
    const response = await api.post(`/platform/recruitment/sources/${source.provider}/sync`, {}, { _skipToast: true })
    const result = response.data?.result ?? {}
    feedback[source.provider] = t('recruitment.sources.syncDone', 'Synchronisation terminée.')
      + ` ${result.imported ?? 0} ${t('recruitment.sources.imported', 'importées')},`
      + ` ${result.closed ?? 0} ${t('recruitment.sources.closed', 'clôturées')}.`
  } catch (e) {
    feedback[source.provider] = e?.response?.data?.result?.message
      || e?.response?.data?.message
      || t('recruitment.sources.syncFailed', 'La synchronisation a échoué.')
  } finally {
    syncing.value = null
    await load()
  }
}

async function revoke(source) {
  try {
    await api.delete(`/platform/recruitment/sources/${source.provider}`, { _skipToast: true })
  } catch {
    // ignore : le rechargement reflète l'état réel
  }
  await load()
}

onMounted(() => load())
</script>
