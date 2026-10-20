<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const status = computed(() => page.props.flash?.status);
</script>

<template>
    <div
        v-if="status"
        class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800"
    >
        {{ status }}
    </div>
</template>
