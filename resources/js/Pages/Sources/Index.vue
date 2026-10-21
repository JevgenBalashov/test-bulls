<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Badge from './Partials/Badge.vue';
import ConfirmDeleteModal from './Partials/ConfirmDeleteModal.vue';
import FlashMessage from './Partials/FlashMessage.vue';
import SourceForm from './Partials/SourceForm.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    sources: {
        type: Array,
        required: true,
    },
});

const importing = ref(null);
const sourceToDelete = ref(null);
const deleting = ref(false);

const startImport = (source) => {
    router.post(
        route('sources.import', source.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (importing.value = source.id),
            onFinish: () => (importing.value = null),
        },
    );
};

const closeModal = () => (sourceToDelete.value = null);

const destroySource = () => {
    router.delete(route('sources.destroy', sourceToDelete.value.id), {
        onStart: () => (deleting.value = true),
        onFinish: () => {
            deleting.value = false;
            closeModal();
        },
    });
};
</script>

<template>
    <Head title="Sources" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Sources
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <FlashMessage />

                <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                    <div
                        v-if="sources.length === 0"
                        class="px-6 py-12 text-center"
                    >
                        <h3 class="text-base font-medium text-gray-900">
                            No sources yet
                        </h3>

                        <p class="mt-1 text-sm text-gray-600">
                            Add a spreadsheet below, then describe its tabs and
                            target tables.
                        </p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr
                                    class="text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                >
                                    <th class="px-6 py-3">Name</th>
                                    <th class="px-6 py-3">Spreadsheet</th>
                                    <th class="px-6 py-3">Tabs</th>
                                    <th class="px-6 py-3">State</th>
                                    <th class="px-6 py-3">Last import</th>
                                    <th class="px-6 py-3">Latest run</th>
                                    <th class="px-6 py-3">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="source in sources" :key="source.id">
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <Link
                                            :href="
                                                route('sources.show', source.id)
                                            "
                                            class="text-sm font-medium text-indigo-600 hover:text-indigo-900"
                                        >
                                            {{ source.name }}
                                        </Link>
                                    </td>

                                    <td
                                        class="px-6 py-4 font-mono text-xs text-gray-600"
                                    >
                                        {{ source.spreadsheet_id }}
                                    </td>

                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-sm text-gray-600"
                                    >
                                        {{ source.tabs_count }}
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4">
                                        <Badge
                                            :tone="
                                                source.is_active
                                                    ? 'green'
                                                    : 'gray'
                                            "
                                        >
                                            {{
                                                source.is_active
                                                    ? 'Active'
                                                    : 'Paused'
                                            }}
                                        </Badge>
                                    </td>

                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-sm text-gray-600"
                                    >
                                        {{ source.last_imported_at ?? 'never' }}
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4">
                                        <Link
                                            v-if="source.latest_run"
                                            :href="
                                                route(
                                                    'runs.show',
                                                    source.latest_run.id,
                                                )
                                            "
                                        >
                                            <StatusBadge
                                                :status="
                                                    source.latest_run.status
                                                "
                                            />
                                        </Link>

                                        <span
                                            v-else
                                            class="text-sm text-gray-400"
                                        >
                                            no runs
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div
                                            class="flex items-center justify-end gap-2"
                                        >
                                            <SecondaryButton
                                                :disabled="
                                                    importing === source.id
                                                "
                                                @click="startImport(source)"
                                            >
                                                {{
                                                    importing === source.id
                                                        ? 'Queueing'
                                                        : 'Import now'
                                                }}
                                            </SecondaryButton>

                                            <DangerButton
                                                @click="sourceToDelete = source"
                                            >
                                                Delete
                                            </DangerButton>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <header>
                        <h2 class="text-lg font-medium text-gray-900">
                            New source
                        </h2>

                        <p class="mt-1 text-sm text-gray-600">
                            Register a Google Sheets file. Tabs and target
                            tables are configured afterwards.
                        </p>
                    </header>

                    <SourceForm class="mt-6 max-w-3xl" />
                </div>
            </div>
        </div>

        <ConfirmDeleteModal
            :show="sourceToDelete !== null"
            :processing="deleting"
            title="Delete this source?"
            confirm-label="Delete source"
            @close="closeModal"
            @confirm="destroySource"
        >
            The configuration of
            <span class="font-medium">{{ sourceToDelete?.name }}</span>
            and its import history will be removed. Target tables and the rows
            already imported are left untouched.
        </ConfirmDeleteModal>
    </AuthenticatedLayout>
</template>
