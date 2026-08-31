<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 py-10"
      role="dialog"
      aria-modal="true"
      :aria-label="title"
      @click.self="$emit('close')"
    >
      <div
        :class="[
          'w-full glass-card p-6 shadow-2xl',
          wide ? 'max-w-3xl' : 'max-w-lg'
        ]"
      >
        <div class="flex items-start justify-between gap-4">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ title }}</h3>
          <button
            class="rounded-md p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300"
            type="button"
            :aria-label="$t('common.close', 'Fermer')"
            @click="$emit('close')"
          >
            <XMarkIcon class="h-5 w-5" />
          </button>
        </div>
        <div class="mt-4">
          <slot />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline'

defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  wide: { type: Boolean, default: false }
})

defineEmits(['close'])
</script>
