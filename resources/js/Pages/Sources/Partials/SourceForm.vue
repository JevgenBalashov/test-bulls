<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    source: {
        type: Object,
        default: null,
    },
});

const uid = props.source ? `source-${props.source.id}` : 'source-new';

const form = useForm({
    name: props.source?.name ?? '',
    spreadsheet_id: props.source?.spreadsheet_id ?? '',
    is_active: props.source ? props.source.is_active : true,
});

const submit = () => {
    if (props.source) {
        form.patch(route('sources.update', props.source.id), {
            preserveScroll: true,
            errorBag: uid,
        });

        return;
    }

    form.post(route('sources.store'), {
        errorBag: uid,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel :for="`${uid}-name`" value="Name" />

                <TextInput
                    :id="`${uid}-name`"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel
                    :for="`${uid}-spreadsheet-id`"
                    value="Spreadsheet ID"
                />

                <TextInput
                    :id="`${uid}-spreadsheet-id`"
                    v-model="form.spreadsheet_id"
                    type="text"
                    class="mt-1 block w-full font-mono text-sm"
                    required
                />

                <p class="mt-1 text-xs text-gray-500">
                    The part of the sheet URL between /d/ and /edit.
                </p>

                <InputError
                    class="mt-2"
                    :message="form.errors.spreadsheet_id"
                />
            </div>
        </div>

        <label class="flex items-center">
            <Checkbox v-model:checked="form.is_active" />
            <span class="ms-2 text-sm text-gray-600">
                Include in the daily import
            </span>
        </label>

        <div class="flex items-center gap-4 border-t border-gray-200 pt-4">
            <PrimaryButton :disabled="form.processing">
                {{ source ? 'Save' : 'Create source' }}
            </PrimaryButton>

            <Transition
                enter-active-class="transition ease-in-out"
                enter-from-class="opacity-0"
                leave-active-class="transition ease-in-out"
                leave-to-class="opacity-0"
            >
                <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">
                    Saved.
                </p>
            </Transition>
        </div>
    </form>
</template>
