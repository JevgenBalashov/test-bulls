<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    sourceId: {
        type: Number,
        default: null,
    },
    tab: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['saved', 'cancel']);

const uid = props.tab ? `tab-${props.tab.id}` : 'tab-new';

const form = useForm({
    tab_name: props.tab?.tab_name ?? '',
    header_row: String(props.tab?.header_row ?? 1),
    data_start_row: String(props.tab?.data_start_row ?? 2),
    is_active: props.tab ? props.tab.is_active : true,
});

const submit = () => {
    if (props.tab) {
        form.patch(route('tabs.update', props.tab.id), {
            preserveScroll: true,
            errorBag: uid,
            onSuccess: () => emit('saved'),
        });

        return;
    }

    form.post(route('tabs.store', props.sourceId), {
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
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <InputLabel :for="`${uid}-name`" value="Tab name" />

                <TextInput
                    :id="`${uid}-name`"
                    v-model="form.tab_name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                />

                <InputError class="mt-2" :message="form.errors.tab_name" />
            </div>

            <div>
                <InputLabel :for="`${uid}-header-row`" value="Header row" />

                <TextInput
                    :id="`${uid}-header-row`"
                    v-model="form.header_row"
                    type="number"
                    min="1"
                    class="mt-1 block w-full"
                    required
                />

                <InputError class="mt-2" :message="form.errors.header_row" />
            </div>

            <div>
                <InputLabel
                    :for="`${uid}-data-start-row`"
                    value="First data row"
                />

                <TextInput
                    :id="`${uid}-data-start-row`"
                    v-model="form.data_start_row"
                    type="number"
                    min="2"
                    class="mt-1 block w-full"
                    required
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.data_start_row"
                />
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center">
                <Checkbox v-model:checked="form.is_active" />
                <span class="ms-2 text-sm text-gray-600">Active</span>
            </label>

            <PrimaryButton :disabled="form.processing">
                {{ tab ? 'Save tab' : 'Add tab' }}
            </PrimaryButton>

            <SecondaryButton v-if="tab" @click="emit('cancel')">
                Cancel
            </SecondaryButton>
        </div>
    </form>
</template>
