<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    tabId: {
        type: Number,
        default: null,
    },
    mapping: {
        type: Object,
        default: null,
    },
    writeModes: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['saved', 'cancel']);

const uid = props.mapping ? `mapping-${props.mapping.id}` : `mapping-new-${props.tabId}`;

const form = useForm({
    target_table: props.mapping?.target_table ?? '',
    write_mode: props.mapping?.write_mode ?? props.writeModes[0],
    is_active: props.mapping ? props.mapping.is_active : true,
});

const submit = () => {
    if (props.mapping) {
        form.patch(route('mappings.update', props.mapping.id), {
            preserveScroll: true,
            errorBag: uid,
            onSuccess: () => emit('saved'),
        });

        return;
    }

    form.post(route('mappings.store', props.tabId), {
        preserveScroll: true,
        errorBag: uid,
        onSuccess: () => {
            form.reset();
            emit('saved');
        },
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel :for="`${uid}-table`" value="Target table" />

                <TextInput
                    :id="`${uid}-table`"
                    v-model="form.target_table"
                    type="text"
                    class="mt-1 block w-full font-mono text-sm"
                    required
                />

                <InputError class="mt-2" :message="form.errors.target_table" />
            </div>

            <div>
                <InputLabel :for="`${uid}-write-mode`" value="Write mode" />

                <select
                    :id="`${uid}-write-mode`"
                    v-model="form.write_mode"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option v-for="mode in writeModes" :key="mode" :value="mode">
                        {{ mode }}
                    </option>
                </select>

                <InputError class="mt-2" :message="form.errors.write_mode" />
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center">
                <Checkbox v-model:checked="form.is_active" />
                <span class="ms-2 text-sm text-gray-600">Active</span>
            </label>

            <PrimaryButton :disabled="form.processing">
                {{ mapping ? 'Save table' : 'Add target table' }}
            </PrimaryButton>

            <SecondaryButton v-if="mapping" @click="emit('cancel')">
                Cancel
            </SecondaryButton>
        </div>
    </form>
</template>
