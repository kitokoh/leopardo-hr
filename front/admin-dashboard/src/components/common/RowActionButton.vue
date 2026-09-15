<script setup>
import { computed } from 'vue'

/**
 * RowActionButton — action de LIGNE unique de la console admin (issue #7434).
 *
 * Convention posée par le propriétaire (2026-09-14) : « si tout est icône,
 * pourquoi lui reste-t-il son texte ? Les seules choses qui peuvent rester
 * icône ET texte, c'est le menu. »
 *
 * Donc : une action de ligne = une icône, jamais de libellé visible ; le nom
 * accessible vient de `label` (i18n) porté par `title` + `aria-label` +
 * `<span class="sr-only">`, pour rester utilisable au clavier et au lecteur
 * d'écran. Le texte reste réservé aux entrées de menu et aux actions
 * primaires de formulaire.
 */
const props = defineProps({
  /** Composant d'icône Heroicons (ex. `PencilSquareIcon`). */
  icon: { type: [Object, Function], required: true },
  /** Nom accessible, déjà traduit (i18n, 4 locales). */
  label: { type: String, required: true },
  /** `neutral` (défaut), `primary` (action principale) ou `danger` (destructif). */
  tone: { type: String, default: 'neutral' },
  size: { type: String, default: 'md' },
  disabled: { type: Boolean, default: false },
  /** Renseigné ⇒ rendu en lien `<a>` (téléchargement, sortie) au lieu d'un bouton. */
  href: { type: String, default: '' },
})

const emit = defineEmits(['click'])

const TONES = {
  neutral: 'text-slate-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-900/30',
  primary: 'text-brand-500 hover:text-brand-700 hover:bg-brand-50 dark:hover:bg-brand-900/30',
  success: 'text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/30',
  warning: 'text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/30',
  danger: 'text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30',
}

const SIZES = {
  sm: 'p-1',
  md: 'p-1.5',
}

const classes = computed(() => [
  'inline-flex items-center justify-center rounded-lg transition-all duration-200',
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-1',
  'disabled:opacity-40 disabled:cursor-not-allowed',
  SIZES[props.size] ?? SIZES.md,
  TONES[props.tone] ?? TONES.neutral,
])
</script>

<template>
  <a
    v-if="href"
    :href="href"
    :class="classes"
    :title="label"
    :aria-label="label"
  >
    <component :is="icon" class="h-4 w-4" aria-hidden="true" />
    <span class="sr-only">{{ label }}</span>
  </a>
  <button
    v-else
    type="button"
    :class="classes"
    :title="label"
    :aria-label="label"
    :disabled="disabled"
    @click="emit('click', $event)"
  >
    <component :is="icon" class="h-4 w-4" aria-hidden="true" />
    <span class="sr-only">{{ label }}</span>
  </button>
</template>
