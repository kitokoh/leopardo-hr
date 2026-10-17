<script setup>
/**
 * Badge de statut du Marketing OS.
 *
 * Toujours COULEUR + LIBELLÉ (jamais la couleur seule) : le vocabulaire
 * affiché est celui des énumérations serveur, donc l'écran ne peut pas
 * diverger de la base. Accessible aux daltoniens et lisible en impression.
 */
const props = defineProps({
  label: { type: String, required: true },
  tone: { type: String, default: 'neutral' }, // neutral | info | warning | success | danger | muted
  title: { type: String, default: '' },
})

const TONES = {
  neutral: 'bg-gray-100 text-gray-700 ring-gray-200',
  info: 'bg-blue-50 text-blue-700 ring-blue-200',
  warning: 'bg-amber-50 text-amber-800 ring-amber-200',
  success: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  danger: 'bg-red-50 text-red-700 ring-red-200',
  muted: 'bg-gray-50 text-gray-500 ring-gray-200',
}
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
    :class="TONES[props.tone] || TONES.neutral"
    :title="props.title || props.label"
  >
    {{ props.label }}
  </span>
</template>
