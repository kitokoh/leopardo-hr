<template>
  <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-3">
    <div :class="['w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0', iconBg]">
      <span :class="[icon, 'w-5 h-5', iconColor]" />
    </div>
    <div>
      <div class="text-xs text-gray-500 dark:text-gray-400">{{ label }}</div>
      <div class="text-xl font-bold text-gray-900 dark:text-white">{{ value }}</div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  label: { type: String, required: true },
  value: { type: Number, required: true },
  icon: { type: String, required: true },
  color: { type: String, default: 'indigo' },
});

const bgMap = {
  indigo: 'bg-indigo-50 dark:bg-indigo-900/20',
  green: 'bg-green-50 dark:bg-green-900/20',
  gray: 'bg-gray-100 dark:bg-gray-700',
  red: 'bg-red-50 dark:bg-red-900/20',
  yellow: 'bg-yellow-50 dark:bg-yellow-900/20',
};

const colorMap = {
  indigo: 'text-indigo-500',
  green: 'text-green-500',
  gray: 'text-gray-400',
  red: 'text-red-500',
  yellow: 'text-yellow-500',
};

const iconBg = computed(() => bgMap[props.color] ?? bgMap.indigo);
const iconColor = computed(() => colorMap[props.color] ?? colorMap.indigo);
</script>
