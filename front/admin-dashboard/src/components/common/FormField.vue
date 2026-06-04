<template>
  <!-- W6 — Accessible form field with inline error feedback -->
  <div class="space-y-1">
    <label
      v-if="label"
      :for="fieldId"
      class="block text-sm font-semibold text-slate-700 dark:text-slate-300 ml-1 mb-1.5"
    >
      {{ label }}
      <span v-if="required" class="text-red-500 ml-0.5" aria-hidden="true">*</span>
      <span v-if="required" class="sr-only">(obligatoire)</span>
    </label>

    <div class="relative">
      <slot :id="fieldId" :aria-invalid="!!error" :aria-describedby="error ? errorId : hint ? hintId : undefined" />
    </div>

    <p
      v-if="hint && !error"
      :id="hintId"
      class="text-xs font-medium text-slate-500 dark:text-slate-400 ml-1 mt-1"
    >
      {{ hint }}
    </p>

    <p
      v-if="error"
      :id="errorId"
      role="alert"
      class="text-xs text-red-600 dark:text-red-400 flex items-center gap-1"
    >
      <svg class="h-3.5 w-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
      </svg>
      {{ error }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: { type: String, default: '' },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
  name: { type: String, default: '' },
})

const fieldId = computed(() => `field-${props.name || Math.random().toString(36).slice(2, 9)}`)
const errorId = computed(() => `${fieldId.value}-error`)
const hintId = computed(() => `${fieldId.value}-hint`)
</script>
