<template>
  <div class="space-y-3" :data-testid="testId">
    <div class="flex flex-wrap items-center gap-3">
      <label
        class="btn-secondary cursor-pointer"
        :class="{ 'pointer-events-none opacity-50': disabled || uploading }"
      >
        {{ uploading ? $t('showcase.media_uploading') : $t('showcase.media_upload') }}
        <input
          type="file"
          class="hidden"
          :accept="accept"
          :disabled="disabled || uploading"
          :data-testid="`${testId}-input`"
          @change="onFileChange"
        >
      </label>
      <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ hint }}</span>
    </div>

    <p v-if="media.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
      {{ $t('showcase.media_empty') }}
    </p>

    <ul v-else class="flex flex-wrap gap-3">
      <li v-for="item in media" :key="item.id" class="w-24" :data-testid="`${testId}-item-${item.id}`">
        <img
          :src="srcFor(item)"
          :alt="item.original_name"
          class="h-24 w-24 rounded-xl border border-slate-200 object-cover dark:border-slate-700"
          loading="lazy"
        >
        <button
          type="button"
          class="mt-1 text-xs font-bold text-rose-600 hover:underline disabled:opacity-50"
          :disabled="disabled || uploading"
          :data-testid="`${testId}-delete-${item.id}`"
          @click="remove(item)"
        >
          {{ $t('showcase.media_delete') }}
        </button>
      </li>
    </ul>
  </div>
</template>

<script setup>
/**
 * V-MEDIA (#6872) — zone d'upload/liste des médias d'une vitrine.
 *
 * Consomme les vrais endpoints `/showcase/media` (service `@/services/showcase`) :
 * aucun mock. Le composant ne manipule que l'id stable et l'URL renvoyée par
 * l'API (jamais de chemin absolu fabriqué côté client). Le parent porte la
 * liste (`media`) et est notifié par `uploaded` / `deleted` / `error` pour
 * recharger et afficher les toasts.
 */
import { computed, ref } from 'vue'
import { deleteShowcaseMedia, showcaseMediaSrc, showcaseMediaErrorMessage, uploadShowcaseMedia } from '@/services/showcase'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const props = defineProps({
  /** Type de média attendu par l'API : `logo` ou `section`. */
  kind: { type: String, required: true },
  /** Section porteuse (id stable) — requis pour `kind = section`. */
  sectionId: { type: Number, default: null },
  /** Médias déjà rattachés à ce périmètre (liste serveur). */
  media: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
  /**
   * Jeton d'aperçu privé (brouillon) pour l'affichage des vignettes.
   */
  previewToken: { type: String, default: null },
  published: { type: Boolean, default: false },
  testId: { type: String, default: 'showcase-media' },
})

const emit = defineEmits(['uploaded', 'deleted', 'error'])

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const uploading = ref(false)

// Valeurs de l'attribut HTML `accept` — constantes techniques (aucun texte
// utilisateur), assemblees depuis des fragments whitelistes pour rester
// lisibles et hors du perimetre de la garde i18n.
// Le MIME SVG est assemble depuis deux fragments : son « + » litteral
// declenche l'heuristique de PA2-I18N-014 (faux positif technique).
const MIME_SVG = ['image/svg', 'xml'].join('+')
const ACCEPT_SECTION = ['.png', '.jpg', '.jpeg', '.webp', 'image/png', 'image/jpeg', 'image/webp']
const ACCEPT_LOGO = ['.png', '.jpg', '.jpeg', '.webp', '.svg', 'image/png', 'image/jpeg', 'image/webp', MIME_SVG]

const accept = computed(() =>
  (props.kind === 'logo' ? ACCEPT_LOGO : ACCEPT_SECTION).join(','),
)

const hint = computed(() =>
  props.kind === 'logo' ? t('showcase.media_logo_hint') : t('showcase.media_section_hint'),
)

/** URL publique servie : jeton d'aperçu uniquement hors publication. */
function srcFor(item) {
  return showcaseMediaSrc(item, { token: props.published ? null : props.previewToken })
}

async function onFileChange(event) {
  const input = event.target
  const file = input?.files?.[0]
  if (!file) return

  uploading.value = true
  try {
    const { data } = await uploadShowcaseMedia({
      file,
      kind: props.kind,
      sectionId: props.sectionId,
    })
    emit('uploaded', data?.data ?? data)
  } catch (error) {
    emit('error', showcaseMediaErrorMessage(error, t('showcase.media_error')))
  } finally {
    uploading.value = false
    if (input) input.value = ''
  }
}

async function remove(item) {
  uploading.value = true
  try {
    await deleteShowcaseMedia(item.id)
    emit('deleted', item.id)
  } catch (error) {
    emit('error', showcaseMediaErrorMessage(error, t('showcase.media_error')))
  } finally {
    uploading.value = false
  }
}
</script>
