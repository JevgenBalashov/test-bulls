<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Badge from './Badge.vue';
import ConfirmDeleteModal from './ConfirmDeleteModal.vue';
import MappingCard from './MappingCard.vue';
import MappingForm from './MappingForm.vue';
import TabForm from './TabForm.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';

const props = defineProps({
    tab: {
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

const editing = ref(false);
const confirmingDeletion = ref(false);
const loadingHeaders = ref(false);
const headersError = ref('');
const preview = ref(null);
const mappingCards = ref([]);

const loadHeaders = async () => {
    loadingHeaders.value = true;
    headersError.value = '';

    try {
        const response = await axios.get(route('tabs.headers', props.tab.id));

        preview.value = response.data;
    } catch (error) {
        preview.value = null;
        headersError.value =
            error.response?.data?.message ??
            'The spreadsheet could not be read.';
    } finally {
        loadingHeaders.value = false;
    }
};

const useHeader = (header) => {
    mappingCards.value.forEach((card) => card?.fillSourceColumn(header));
};

const destroyTab = () => {
    router.delete(route('tabs.destroy', props.tab.id), {
        preserveScroll: true,
        onFinish: () => (confirmingDeletion.value = false),
    });
};
</script>

<template>
    <div class="rounded-lg border border-gray-200">
        <div
            class="flex flex-wrap items-start justify-between gap-3 rounded-t-lg border-b border-gray-200 bg-gray-50 px-4 py-3"
        >
            <div>
                <h3 class="text-base font-medium text-gray-900">
                    {{ tab.tab_name }}
                </h3>

                <p class="mt-1 text-xs text-gray-500">
                    Headers on row {{ tab.header_row }}, data from row
                    {{ tab.data_start_row }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Badge :tone="tab.is_active ? 'green' : 'gray'">
                    {{ tab.is_active ? 'Active' : 'Paused' }}
                </Badge>

                <SecondaryButton
                    :disabled="loadingHeaders"
                    @click="loadHeaders"
                >
                    {{
                        loadingHeaders ? 'Loading' : 'Load headers from Google'
                    }}
                </SecondaryButton>

                <SecondaryButton @click="editing = !editing">
                    Edit
                </SecondaryButton>

                <DangerButton @click="confirmingDeletion = true">
                    Delete
                </DangerButton>
            </div>
        </div>

        <div class="space-y-5 px-4 py-4">
            <TabForm
                v-if="editing"
                :tab="tab"
                class="rounded-md bg-gray-50 p-4"
                @saved="editing = false"
                @cancel="editing = false"
            />

            <InputError :message="headersError" />

            <div v-if="preview" class="rounded-md bg-indigo-50 px-4 py-3">
                <p class="text-xs text-indigo-900">
                    {{ preview.rows }} data rows found. Click a header to fill
                    the sheet header field below.
                </p>

                <div
                    v-if="preview.headers.length"
                    class="mt-2 flex flex-wrap gap-2"
                >
                    <button
                        v-for="(header, index) in preview.headers"
                        :key="index"
                        type="button"
                        class="rounded-full bg-white px-3 py-1 text-xs font-medium text-indigo-700 shadow-sm transition hover:bg-indigo-100"
                        @click="useHeader(header)"
                    >
                        {{ header }}
                    </button>
                </div>

                <p v-else class="mt-2 text-xs text-indigo-900">
                    The header row is empty.
                </p>
            </div>

            <div class="space-y-4">
                <MappingCard
                    v-for="mapping in tab.mappings"
                    :key="mapping.id"
                    ref="mappingCards"
                    :mapping="mapping"
                    :data-types="dataTypes"
                    :write-modes="writeModes"
                />

                <p
                    v-if="tab.mappings.length === 0"
                    class="text-sm text-gray-500"
                >
                    This tab is not written anywhere yet. Add a target table
                    below.
                </p>
            </div>

            <div class="rounded-md border border-dashed border-gray-300 p-4">
                <h4 class="text-sm font-medium text-gray-900">
                    New target table
                </h4>

                <MappingForm
                    :tab-id="tab.id"
                    :write-modes="writeModes"
                    class="mt-4"
                />
            </div>
        </div>

        <ConfirmDeleteModal
            :show="confirmingDeletion"
            title="Delete this tab?"
            confirm-label="Delete tab"
            @close="confirmingDeletion = false"
            @confirm="destroyTab"
        >
            <span class="font-medium">{{ tab.tab_name }}</span>
            and every target table mapped to it will be removed from the
            configuration.
        </ConfirmDeleteModal>
    </div>
</template>
