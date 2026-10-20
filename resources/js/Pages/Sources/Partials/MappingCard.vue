<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Badge from './Badge.vue';
import ColumnRow from './ColumnRow.vue';
import ConfirmDeleteModal from './ConfirmDeleteModal.vue';
import MappingForm from './MappingForm.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    mapping: {
        type: Object,
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

const uid = `mapping-${props.mapping.id}`;

const editing = ref(false);
const confirmingDeletion = ref(false);
const columnToDelete = ref(null);
const sourceColumnInput = ref(null);

const form = useForm({
    source_column: '',
    target_column: '',
    data_type: props.dataTypes[0],
    is_nullable: true,
    is_unique_key: false,
});

const addColumn = () => {
    form.post(route('columns.store', props.mapping.id), {
        preserveScroll: true,
        errorBag: `column-new-${props.mapping.id}`,
        onSuccess: () => form.reset(),
    });
};

const destroyMapping = () => {
    router.delete(route('mappings.destroy', props.mapping.id), {
        preserveScroll: true,
        onFinish: () => (confirmingDeletion.value = false),
    });
};

const destroyColumn = () => {
    router.delete(route('columns.destroy', columnToDelete.value.id), {
        preserveScroll: true,
        onFinish: () => (columnToDelete.value = null),
    });
};

const fillSourceColumn = (header) => {
    form.source_column = header;
    sourceColumnInput.value?.focus();
};

defineExpose({ fillSourceColumn });
</script>

<template>
    <div class="rounded-md border border-gray-200">
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3"
        >
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-sm font-medium text-gray-900">
                    {{ mapping.target_table }}
                </span>

                <Badge tone="indigo">{{ mapping.write_mode }}</Badge>

                <Badge :tone="mapping.is_active ? 'green' : 'gray'">
                    {{ mapping.is_active ? 'Active' : 'Paused' }}
                </Badge>

                <Badge v-if="!mapping.table_exists" tone="amber">
                    table not created yet
                </Badge>
            </div>

            <div class="flex items-center gap-2">
                <SecondaryButton @click="editing = !editing">
                    Edit
                </SecondaryButton>

                <DangerButton @click="confirmingDeletion = true">
                    Remove table
                </DangerButton>
            </div>
        </div>

        <div class="space-y-4 px-4 py-4">
            <MappingForm
                v-if="editing"
                :mapping="mapping"
                :write-modes="writeModes"
                class="rounded-md bg-gray-50 p-4"
                @saved="editing = false"
                @cancel="editing = false"
            />

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr
                            class="text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                        >
                            <th class="px-3 py-2">Sheet header</th>
                            <th class="px-3 py-2">Table column</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2 text-center">Nullable</th>
                            <th class="px-3 py-2 text-center">Unique key</th>
                            <th class="px-3 py-2">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        <ColumnRow
                            v-for="column in mapping.columns"
                            :key="column.id"
                            :column="column"
                            :data-types="dataTypes"
                            @remove="columnToDelete = column"
                        />

                        <tr v-if="mapping.columns.length === 0">
                            <td
                                colspan="6"
                                class="px-3 py-4 text-sm text-gray-500"
                            >
                                No columns mapped yet, nothing will be written
                                to this table.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form
                @submit.prevent="addColumn"
                class="space-y-4 rounded-md bg-gray-50 p-4"
            >
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <InputLabel
                            :for="`${uid}-source-column`"
                            value="Sheet header"
                        />

                        <TextInput
                            :id="`${uid}-source-column`"
                            ref="sourceColumnInput"
                            v-model="form.source_column"
                            type="text"
                            class="mt-1 block w-full text-sm"
                            required
                        />

                        <InputError
                            class="mt-2"
                            :message="form.errors.source_column"
                        />
                    </div>

                    <div>
                        <InputLabel
                            :for="`${uid}-target-column`"
                            value="Table column"
                        />

                        <TextInput
                            :id="`${uid}-target-column`"
                            v-model="form.target_column"
                            type="text"
                            class="mt-1 block w-full font-mono text-sm"
                            required
                        />

                        <InputError
                            class="mt-2"
                            :message="form.errors.target_column"
                        />
                    </div>

                    <div>
                        <InputLabel
                            :for="`${uid}-data-type`"
                            value="Data type"
                        />

                        <select
                            :id="`${uid}-data-type`"
                            v-model="form.data_type"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option
                                v-for="type in dataTypes"
                                :key="type"
                                :value="type"
                            >
                                {{ type }}
                            </option>
                        </select>

                        <InputError
                            class="mt-2"
                            :message="form.errors.data_type"
                        />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <label class="flex items-center">
                        <Checkbox v-model:checked="form.is_nullable" />
                        <span class="ms-2 text-sm text-gray-600">
                            Nullable
                        </span>
                    </label>

                    <label class="flex items-center">
                        <Checkbox v-model:checked="form.is_unique_key" />
                        <span class="ms-2 text-sm text-gray-600">
                            Unique key
                        </span>
                    </label>

                    <PrimaryButton :disabled="form.processing">
                        Add column
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <ConfirmDeleteModal
            :show="confirmingDeletion"
            title="Remove this target table?"
            confirm-label="Remove table"
            @close="confirmingDeletion = false"
            @confirm="destroyMapping"
        >
            The mapping for
            <span class="font-mono">{{ mapping.target_table }}</span>
            and all of its columns will be deleted from the configuration. The
            table itself stays in the database.
        </ConfirmDeleteModal>

        <ConfirmDeleteModal
            :show="columnToDelete !== null"
            title="Remove this column?"
            confirm-label="Remove column"
            @close="columnToDelete = null"
            @confirm="destroyColumn"
        >
            <span class="font-mono">{{ columnToDelete?.target_column }}</span>
            will no longer be filled by the importer.
        </ConfirmDeleteModal>
    </div>
</template>
