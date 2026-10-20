<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Badge from './Badge.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    column: {
        type: Object,
        required: true,
    },
    dataTypes: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['remove']);

const editing = ref(false);

const form = useForm({
    source_column: props.column.source_column,
    target_column: props.column.target_column,
    data_type: props.column.data_type,
    is_nullable: props.column.is_nullable,
    is_unique_key: props.column.is_unique_key,
});

const submit = () => {
    form.patch(route('columns.update', props.column.id), {
        preserveScroll: true,
        errorBag: `column-${props.column.id}`,
        onSuccess: () => (editing.value = false),
    });
};

const cancel = () => {
    editing.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <tr v-if="editing" class="bg-gray-50 align-top">
        <td class="px-3 py-3">
            <TextInput
                v-model="form.source_column"
                type="text"
                class="block w-full text-sm"
                aria-label="Source column"
                required
            />

            <InputError class="mt-1" :message="form.errors.source_column" />
        </td>

        <td class="px-3 py-3">
            <TextInput
                v-model="form.target_column"
                type="text"
                class="block w-full font-mono text-sm"
                aria-label="Target column"
                required
            />

            <InputError class="mt-1" :message="form.errors.target_column" />
        </td>

        <td class="px-3 py-3">
            <select
                v-model="form.data_type"
                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                aria-label="Data type"
            >
                <option v-for="type in dataTypes" :key="type" :value="type">
                    {{ type }}
                </option>
            </select>

            <InputError class="mt-1" :message="form.errors.data_type" />
        </td>

        <td class="px-3 py-3 text-center">
            <Checkbox v-model:checked="form.is_nullable" />
        </td>

        <td class="px-3 py-3 text-center">
            <Checkbox v-model:checked="form.is_unique_key" />
        </td>

        <td class="px-3 py-3">
            <div class="flex items-center justify-end gap-2">
                <PrimaryButton :disabled="form.processing" @click="submit">
                    Save
                </PrimaryButton>

                <SecondaryButton @click="cancel">Cancel</SecondaryButton>
            </div>
        </td>
    </tr>

    <tr v-else :class="{ 'bg-amber-50': column.pending_in_table }">
        <td class="px-3 py-2 text-sm text-gray-700">
            {{ column.source_column }}
        </td>

        <td class="px-3 py-2 text-sm">
            <span class="font-mono text-gray-900">
                {{ column.target_column }}
            </span>

            <Badge
                v-if="column.pending_in_table"
                tone="amber"
                class="ms-2"
            >
                will be added
            </Badge>
        </td>

        <td class="px-3 py-2 text-sm text-gray-600">
            {{ column.data_type }}
        </td>

        <td class="px-3 py-2 text-center text-sm text-gray-600">
            {{ column.is_nullable ? 'yes' : 'no' }}
        </td>

        <td class="px-3 py-2 text-center text-sm text-gray-600">
            {{ column.is_unique_key ? 'yes' : 'no' }}
        </td>

        <td class="px-3 py-2">
            <div class="flex items-center justify-end gap-2">
                <SecondaryButton @click="editing = true">Edit</SecondaryButton>

                <SecondaryButton @click="emit('remove', column)">
                    Remove
                </SecondaryButton>
            </div>
        </td>
    </tr>
</template>
