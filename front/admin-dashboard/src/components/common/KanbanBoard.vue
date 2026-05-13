<template>
  <div class="flex gap-4 overflow-x-auto pb-4">
    <div
      v-for="column in columns"
      :key="column.key"
      class="w-72 flex-shrink-0 rounded-lg bg-gray-50 p-3"
    >
      <div class="mb-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700">{{ column.label }}</h3>
        <span class="inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700">
          {{ columnItems(column.key).length }}
        </span>
      </div>
      <div class="space-y-2">
        <div
          v-for="item in columnItems(column.key)"
          :key="item[itemKey]"
          class="cursor-pointer rounded-md bg-white p-3 shadow-sm ring-1 ring-gray-200 transition hover:shadow-md"
          @click="$emit('item-click', item)"
        >
          <slot name="card" :item="item">
            <p class="text-sm font-medium text-gray-900">{{ item.title || item.name }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ item.subtitle || '' }}</p>
          </slot>
        </div>
        <div v-if="columnItems(column.key).length === 0" class="py-4 text-center text-xs text-gray-400">
          Aucun element
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  columns: { type: Array, required: true },
  items: { type: Array, default: () => [] },
  statusField: { type: String, default: 'status' },
  itemKey: { type: String, default: 'id' }
})

defineEmits(['item-click'])

function columnItems(key) {
  return props.items.filter(item => item[props.statusField] === key)
}
</script>
