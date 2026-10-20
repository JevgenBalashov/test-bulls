<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Badge from './Partials/Badge.vue';
import FlashMessage from './Partials/FlashMessage.vue';
import SourceForm from './Partials/SourceForm.vue';
import TabCard from './Partials/TabCard.vue';
import TabForm from './Partials/TabForm.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    source: {
        type: Object,
        required: true,
    },
    tabs: {
        type: Array,
        required: true,
    },
    dataTypes: {
        type: Array,
        required: true,
    },
    writeModes: {
        type: Array,
        required: true,
    },
});

const importing = ref(false);

const startImport = () => {
    router.post(route('sources.import', props.source.id), {}, {
        preserveScroll: true,
        onStart: () => (importing.value = true),
        onFinish: () => (importing.value = false),
    });
};
</script>

<template>
    <Head :title="source.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800"
                    >
                        {{ source.name }}
                    </h2>

                    <p class="mt-1 font-mono text-xs text-gray-500">
                        {{ source.spreadsheet_id }}
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Badge :tone="source.is_active ? 'green' : 'gray'">
                        {{ source.is_active ? 'Active' : 'Paused' }}
                    </Badge>

                    <span class="text-xs text-gray-500">
                        Last import: {{ source.last_imported_at ?? 'never' }}
                    </span>

                    <PrimaryButton :disabled="importing" @click="startImport">
                        {{ importing ? 'Queueing' : 'Import now' }}
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <FlashMessage />

                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <header class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-medium text-gray-900">
                                Source
                            </h2>

                            <p class="mt-1 text-sm text-gray-600">
                                The service account has to be granted read
                                access to this file.
                            </p>
                        </div>

                        <Link
                            :href="route('sources.index')"
                            class="text-sm text-gray-600 underline hover:text-gray-900"
                        >
                            All sources
                        </Link>
                    </header>

                    <SourceForm
                        :key="source.id"
                        :source="source"
                        class="mt-6 max-w-3xl"
                    />
                </div>

                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <header>
                        <h2 class="text-lg font-medium text-gray-900">
                            Tabs
                        </h2>

                        <p class="mt-1 text-sm text-gray-600">
                            Columns are matched by header name, so the order of
                            the columns in the sheet does not matter.
                        </p>
                    </header>

                    <div class="mt-6 space-y-6">
                        <TabCard
                            v-for="tab in tabs"
                            :key="tab.id"
                            :tab="tab"
                            :data-types="dataTypes"
                            :write-modes="writeModes"
                        />

                        <p
                            v-if="tabs.length === 0"
                            class="text-sm text-gray-500"
                        >
                            No tabs configured yet.
                        </p>
                    </div>
                </div>

                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <header>
                        <h2 class="text-lg font-medium text-gray-900">
                            New tab
                        </h2>

                        <p class="mt-1 text-sm text-gray-600">
                            The tab name must match the sheet name inside the
                            spreadsheet.
                        </p>
                    </header>

                    <TabForm
                        :source-id="source.id"
                        class="mt-6 max-w-4xl"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
