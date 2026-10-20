<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import RunItemsTable from './Partials/RunItemsTable.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    run: {
        type: Object,
        required: true,
    },
});

const schemaChanges = computed(() =>
    props.run.items.filter((item) => item.added_columns.length > 0),
);

const addedColumnsCount = computed(() =>
    schemaChanges.value.reduce(
        (total, item) => total + item.added_columns.length,
        0,
    ),
);
</script>

<template>
    <Head :title="`Run #${run.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Run #{{ run.id }} &mdash; {{ run.source.name }}
                </h2>

                <Link
                    :href="route('runs.index')"
                    class="text-sm text-gray-500 underline hover:text-gray-700"
                >
                    Back to import runs
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <dl
                        class="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-5"
                    >
                        <div>
                            <dt
                                class="text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                Status
                            </dt>
                            <dd class="mt-1">
                                <StatusBadge :status="run.status" />
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                Started
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ run.started_at ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                Finished
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ run.finished_at ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                Rows read
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ run.rows_read }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                Rows written
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ run.rows_written }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    v-if="run.error_message"
                    class="border border-red-200 bg-red-50 p-6 shadow sm:rounded-lg"
                >
                    <h3 class="text-sm font-semibold text-red-800">
                        The run failed
                    </h3>
                    <p class="mt-2 whitespace-pre-line text-sm text-red-700">
                        {{ run.error_message }}
                    </p>
                </div>

                <div
                    v-if="schemaChanges.length > 0"
                    class="border border-indigo-200 bg-indigo-50 p-6 shadow sm:rounded-lg"
                >
                    <h3 class="text-sm font-semibold text-indigo-900">
                        Schema changes
                    </h3>
                    <p class="mt-1 text-sm text-indigo-800">
                        {{ addedColumnsCount }} column(s) were added to the
                        target tables during this run.
                    </p>

                    <ul class="mt-4 space-y-2">
                        <li
                            v-for="item in schemaChanges"
                            :key="item.id"
                            class="flex flex-wrap items-center gap-2"
                        >
                            <code
                                class="rounded bg-white px-1.5 py-0.5 text-xs font-semibold text-indigo-900"
                            >
                                {{ item.target_table }}
                            </code>
                            <span
                                v-for="column in item.added_columns"
                                :key="column"
                                class="inline-flex items-center rounded-md border border-indigo-300 bg-white px-2 py-0.5 font-mono text-xs font-semibold text-indigo-800"
                            >
                                + {{ column }}
                            </span>
                        </li>
                    </ul>
                </div>

                <div class="bg-white shadow sm:rounded-lg">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">
                            Mapped tables
                        </h3>
                    </div>

                    <div
                        v-if="run.items.length === 0"
                        class="p-6 text-center text-sm text-gray-500"
                    >
                        This run produced no items. Check that the source has
                        active tabs with at least one mapping.
                    </div>

                    <RunItemsTable v-else :items="run.items" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
