<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from './Partials/Pagination.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    runs: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Import runs" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Import runs
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white shadow sm:rounded-lg">
                    <div
                        v-if="runs.data.length === 0"
                        class="p-6 text-center text-sm text-gray-500"
                    >
                        No import runs yet. A run appears here every time the
                        importer executes, either on schedule or on demand.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                    >
                                        Run
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                    >
                                        Source
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                    >
                                        Status
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                    >
                                        Started
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                    >
                                        Finished
                                    </th>
                                    <th
                                        class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500"
                                    >
                                        Rows read
                                    </th>
                                    <th
                                        class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500"
                                    >
                                        Rows written
                                    </th>
                                    <th
                                        class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500"
                                    >
                                        Items
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="run in runs.data"
                                    :key="run.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <Link
                                            :href="route('runs.show', run.id)"
                                            class="text-sm font-semibold text-indigo-600 hover:text-indigo-900"
                                        >
                                            #{{ run.id }}
                                        </Link>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <Link
                                            :href="
                                                route(
                                                    'sources.show',
                                                    run.source.id,
                                                )
                                            "
                                            class="text-sm text-gray-700 underline decoration-gray-300 hover:text-gray-900"
                                        >
                                            {{ run.source.name }}
                                        </Link>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <StatusBadge :status="run.status" />
                                    </td>
                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-sm text-gray-500"
                                    >
                                        {{ run.started_at ?? '—' }}
                                    </td>
                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-sm text-gray-500"
                                    >
                                        {{ run.finished_at ?? '—' }}
                                    </td>
                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-700"
                                    >
                                        {{ run.rows_read }}
                                    </td>
                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-700"
                                    >
                                        {{ run.rows_written }}
                                    </td>
                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-700"
                                    >
                                        {{ run.items_count }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    v-if="runs.data.length > 0"
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-gray-500">
                        Page {{ runs.current_page }} of {{ runs.last_page }},
                        {{ runs.total }} runs in total
                    </p>

                    <Pagination :links="runs.links" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
