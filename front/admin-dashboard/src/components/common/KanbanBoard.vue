<template>
  <div class="flex gap-4 overflow-x-auto pb-4">
    <div
      v-for="column in columns"
      :key="column.key"
      class="w-72 flex-shrink-0 rounded-2xl border border-white/20 bg-white/40 p-3 backdrop-blur-xl transition-colors dark:border-slate-800/50 dark:bg-slate-900/40"
      :class="dragOverColumn === column.key ? 'ring-2 ring-brand-400 bg-brand-50/60 dark:bg-brand-900/20' : ''"
      @dragover.prevent="onDragOver(column.key)"
      @dragleave="onDragLeave(column.key)"
      @drop="onDrop(column.key)"
    >
      <div class="mb-3 flex items-center justify-between">
        <h3 class="text-xs font-black uppercase tracking-widest text-slate-600 dark:text-slate-300">{{ column.label }}</h3>
        <span class="inline-flex items-center rounded-full bg-slate-200/70 px-2 py-0.5 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
          {{ columnItems(column.key).length }}
        </span>
      </div>
      <div class="space-y-2 min-h-[3rem]">
        <div
          v-for="item in columnItems(column.key)"
          :key="item[itemKey]"
          :draggable="draggable"
          class="cursor-pointer rounded-xl border border-white/30 bg-white/80 p-3 shadow-glass-sm ring-1 ring-slate-200/50 transition hover:-translate-y-0.5 hover:shadow-glass dark:border-slate-800/50 dark:bg-slate-900/70 dark:ring-slate-800/50"
          :class="draggingId === item[itemKey] ? 'opacity-40' : ''"
          @click="$emit('item-click', item)"
          @dragstart="onDragStart(item)"
          @dragend="onDragEnd"
        >
          <slot name="card" :item="item">
            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ item.title || item.name }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ item.subtitle || '' }}</p>
          </slot>
        </div>
        <div v-if="columnItems(column.key).length === 0" class="py-4 text-center text-xs text-slate-400 dark:text-slate-500">
          {{ emptyLabel }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  columns: { type: Array, required: true },
  items: { type: Array, default: () => [] },
  statusField: { type: String, default: 'status' },
  itemKey: { type: String, default: 'id' },
  draggable: { type: Boolean, default: true },
  emptyLabel: { type: String, default: 'No items' },
})

const emit = defineEmits(['item-click', 'move'])

const draggingItem = ref(null)
const draggingId = ref(null)
const dragOverColumn = ref('')

function columnItems(key) {
  return props.items.filter(item => item[props.statusField] === key)
}

function onDragStart(item) {
  if (!props.draggable) return
  draggingItem.value = item
  draggingId.value = item[props.itemKey]
}

function onDragEnd() {
  draggingItem.value = null
  draggingId.value = null
  dragOverColumn.value = ''
}

function onDragOver(columnKey) {
  if (!props.draggable || !draggingItem.value) return
  dragOverColumn.value = columnKey
}

function onDragLeave(columnKey) {
  if (dragOverColumn.value === columnKey) {
    dragOverColumn.value = ''
  }
}

function onDrop(columnKey) {
  if (!props.draggable || !draggingItem.value) return
  const item = draggingItem.value
  const fromStatus = item[props.statusField]
  dragOverColumn.value = ''
  draggingItem.value = null
  draggingId.value = null
  if (fromStatus === columnKey) return
  emit('move', { item, from: fromStatus, to: columnKey })
}
</script>
