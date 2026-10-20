<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        default: null,
    },
});

const styles = {
    success: 'bg-green-100 text-green-800',
    partial: 'bg-amber-100 text-amber-800',
    failed: 'bg-red-100 text-red-800',
    running: 'bg-blue-100 text-blue-800',
    pending: 'bg-gray-100 text-gray-800',
};

const classes = computed(() => styles[props.status] ?? styles.pending);

const label = computed(() => props.status ?? 'unknown');
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
        :class="classes"
    >
        {{ label }}
    </span>
</template>
