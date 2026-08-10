<template>
  <div>
    <label v-if="label" :for="id" class="block text-sm font-medium text-gray-700">
      {{ label }}<span v-if="required" class="text-red-500" aria-hidden="true"> *</span>
    </label>

    <slot
      :id="id"
      :aria-invalid="ariaInvalid"
      :aria-describedby="describedBy"
      :described-by="describedBy"
      :error="error"
    />

    <p
      v-if="error"
      :id="`${id}-error`"
      role="alert"
      class="mt-1 text-sm text-red-600"
    >
      {{ error }}
    </p>
    <p v-else-if="hint" :id="`${id}-hint`" class="mt-1 text-xs text-gray-500">
      {{ hint }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

/**
 * S-6 (#1666) — Wrapper champ de formulaire accessible (WCAG).
 *
 * Associe label + erreur inline à l'input via le slot :
 *  - `aria-invalid`   → signalé à l'utilisateur quand le champ est en erreur ;
 *  - `aria-describedby` → pointe vers le message d'erreur (`{id}-error`) ou
 *    d'aide (`{id}-hint`) ;
 *  - le message d'erreur porte `role="alert"` (annonce par lecteur d'écran).
 *
 * Usage :
 *   <FormField id="email" label="Email" :error="errors.email" required v-slot="{ ariaInvalid, describedBy }">
 *     <input v-model="form.email" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" />
 *   </FormField>
 */
const props = defineProps({
  id: { type: String, required: true },
  label: { type: String, default: '' },
  error: { type: String, default: null },
  hint: { type: String, default: null },
  required: { type: Boolean, default: false },
})

const describedBy = computed(() => {
  if (props.error) return `${props.id}-error`
  if (props.hint) return `${props.id}-hint`
  return undefined
})

const ariaInvalid = computed(() => (props.error ? true : undefined))
</script>
