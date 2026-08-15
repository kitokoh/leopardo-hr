<template>
<!-- eslint-disable vue/no-mutating-props -- objet reactif du parent (pattern etabli) -->
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
          :for="fieldId(provider, 'client-id')"
          class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5"
        >{{ labelClientId }}</label>
        <input
          :id="fieldId(provider, 'client-id')"
          v-model="form.clientId"
          type="text"
          class="form-input"
          autocomplete="off"
          :placeholder="placeholderId"
        />
      </div>
      <div>
        <label
          :for="fieldId(provider, 'client-secret')"
          class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5"
        >
          {{ labelClientSecret }}
          <span class="ml-1 text-xs font-normal text-slate-400">{{ labelSecretHint }}</span>
        </label>
        <input
          :id="fieldId(provider, 'client-secret')"
          v-model="form.clientSecret"
          type="password"
          class="form-input"
          autocomplete="new-password"
          :placeholder="placeholderSecret"
        />
      </div>
      <div>
        <label
          :for="fieldId(provider, 'redirect-uri')"
          class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5"
        >{{ labelRedirectUri }}</label>
        <input
          :id="fieldId(provider, 'redirect-uri')"
          v-model="form.redirectUri"
          type="url"
          class="form-input"
          :placeholder="placeholderUri"
        />
      </div>
      <div class="flex justify-end">
        <button type="submit" class="btn-primary" :disabled="loading">
          <span
            v-if="loading"
            class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent inline-block"
          ></span>
          {{ labelSave }}
        </button>
      </div>
    </form>
  </div>
<!-- eslint-enable vue/no-mutating-props -->
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
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
})

defineEmits(['save'])

function fieldId(provider, field) {
  return provider + '-' + field
}

const providerInitial = computed(() => props.label.charAt(0).toUpperCase())
</script>
