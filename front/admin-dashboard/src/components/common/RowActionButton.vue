<template>
  <a
    v-if="href"
    :href="href"
    class="inline-flex items-center justify-center rounded-lg p-1.5 transition-all duration-200"
    :class="toneClass"
    :title="label"
    :aria-label="label"
    :data-testid="testId || undefined"
  >
    <component :is="icon" class="h-4 w-4" aria-hidden="true" />
    <span class="sr-only">{{ label }}</span>
  </a>
  <button
    v-else
    type="button"
    class="inline-flex items-center justify-center rounded-lg p-1.5 transition-all duration-200 disabled:cursor-not-allowed disabled:opacity-40"
    :class="toneClass"
    :title="label"
    :aria-label="label"
    :disabled="disabled"
    :data-testid="testId || undefined"
    @click="$emit('click')"
  >
    <component :is="icon" class="h-4 w-4" aria-hidden="true" />
    <span class="sr-only">{{ label }}</span>
  </button>
</template>

<script setup>
/*
 * RowActionButton — convention UNIQUE d'action de ligne de la console admin
 * (issue #7434).
 *
 * Règle posée par le propriétaire : « si tout est icône, pourquoi lui
 * reste-t-il son texte ? Les seules choses qui peuvent rester icône ET texte,
 * c'est le menu ». Une action de ligne est donc :
 * - une icône seule (densité + lisibilité des tableaux) ;
 * - avec un `title` (affordance visuelle) ;
 * - avec un `aria-label` + un `<span class="sr-only">` (nom accessible —
 * une icône sans nom est invisible au lecteur d'écran).
 *
 * Le libellé est TOUJOURS internationalisé par l'appelant
 * (`t('…')`), jamais écrit en dur ici (garde check-admin-action-labels.py).
 *
 * `href` renseigné ⇒ l'action est un LIEN (téléchargement, sortie) rendu en
 * `<a>` : même convention visuelle et même nom accessible, mais sémantique de
 * lien conservée (clic milieu, copie d'adresse). C'est le seul cas d'action de
 * ligne qui ne soit pas un `<button>` — cf. `ExportsView` (#7434).
 */
import { computed } from 'vue'

/**
 * Tons sémantiques : `primary` = action neutre de ligne (modifier, gérer),
 * `danger` = destructive (supprimer — à coupler à `useConfirmDialog`),
 * `success`/`warning` = transitions d'état (valider, renouveler).
 */
const TONES = {
  neutral:
    'text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:text-slate-200 dark:hover:bg-slate-800',
  primary:
    'text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:text-slate-400 dark:hover:text-indigo-400 dark:hover:bg-indigo-900/30',
  brand:
    'text-slate-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:text-brand-400 dark:hover:bg-brand-900/30',
  danger:
    'text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:text-red-400 dark:hover:bg-red-900/30',
  success:
    'text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:text-emerald-400 dark:hover:bg-emerald-900/30',
  warning:
    'text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:text-amber-400 dark:hover:bg-amber-900/30',
}

const props = defineProps({
  // Composant d'icône Heroicons (importé par l'appelant).
  icon: { type: [Object, Function], required: true },
  // Libellé accessible — déjà traduit par l'appelant.
  label: { type: String, required: true },
  tone: { type: String, default: 'neutral' },
  disabled: { type: Boolean, default: false },
  testId: { type: String, default: '' },
  // Renseigné ⇒ rendu en lien `<a>` (téléchargement, sortie) au lieu d'un bouton.
  href: { type: String, default: '' },
})

defineEmits(['click'])

const toneClass = computed(() => TONES[props.tone] || TONES.neutral)
</script>
