<script setup>
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                    >
                        Tab
                    </th>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                    >
                        Target table
                    </th>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                    >
                        Status
                    </th>
                    <th
                        class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500"
                    >
                        Read
                    </th>
                    <th
                        class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500"
                    >
                        Written
                    </th>
                    <th
                        class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500"
                    >
                        Skipped
                    </th>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                    >
                        Added columns
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <template v-for="item in items" :key="item.id">
                    <tr>
                        <td
                            class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900"
                        >
                            {{ item.tab_name }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <code
                                class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-800"
                            >
                                {{ item.target_table }}
                            </code>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <StatusBadge :status="item.status" />
                        </td>
                        <td
                            class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-700"
                        >
                            {{ item.rows_read }}
                        </td>
                        <td
                            class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-700"
                        >
                            {{ item.rows_written }}
                        </td>
                        <td
                            class="whitespace-nowrap px-6 py-4 text-right text-sm"
                            :class="
                                item.rows_skipped > 0
                                    ? 'font-semibold text-amber-700'
                                    : 'text-gray-700'
                            "
                        >
                            {{ item.rows_skipped }}
                        </td>
                        <td class="px-6 py-4">
                            <div
                                v-if="item.added_columns.length > 0"
                                class="flex flex-wrap gap-1"
                            >
                                <span
                                    v-for="column in item.added_columns"
                                    :key="column"
                                    class="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-2 py-0.5 font-mono text-xs font-semibold text-indigo-800"
                                >
                                    {{ column }}
                                </span>
                            </div>
                            <span v-else class="text-sm text-gray-400">—</span>
                        </td>
                    </tr>
                    <tr v-if="item.error_message">
                        <td colspan="7" class="px-6 pb-4">
                            <p
                                class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700"
                            >
                                {{ item.error_message }}
                            </p>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</template>
