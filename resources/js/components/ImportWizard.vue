<template>
    <div>
        <!-- Configure -->
        <div v-if="onConfigureStep" class="mt-3 card p-0 overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b dark:bg-dark-650 dark:border-b dark:border-dark-900">
                <h2>Configure</h2>
            </div>

            <div class="p-4">
                <form>
                    <div class="flex flex-col gap-6">
                        <div>
                            <label class="font-semibold text-sm mb-1" for="type">{{ __('Type') }}</label>
                            <select-input
                                name="type"
                                v-model="config.type"
                                :options="types"
                            />
                        </div>

                        <div>
                            <label class="font-semibold text-sm mb-1" for="path">{{ __('Path') }}</label>
                            <div class="help-block">
                                <p>This can be a file in the filesystem, or a remote URL to a file.</p>
                            </div>
                            <input type="text" name="path" v-model="config.path" class="input-text font-mono">
                        </div>

                        <div>
                            <label class="font-semibold text-sm mb-1" for="destination.type">{{ __('Destination') }}</label>
                            <select-input
                                name="destination.type"
                                v-model="config.destination.type"
                                :options="destinationTypes"
                            />
                        </div>

                        <div v-if="config.destination.type === 'entries'">
                            <label class="font-semibold text-sm mb-1" for="destination.collection">{{ __('Collection') }}</label>
                            <select-input
                                name="destination.collection"
                                v-model="config.destination.collection"
                                :options="normalizeInputOptions(collections)"
                            />
                        </div>

                        <div v-if="config.destination.type === 'terms'">
                            <label class="font-semibold text-sm mb-1" for="destination.taxonomy">{{ __('Taxonomy') }}</label>
                            <select-input
                                name="destination.taxonomy"
                                v-model="config.destination.taxonomy"
                                :options="normalizeInputOptions(taxonomies)"
                            />
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-center space-x-2">
                        <button class="btn-primary" type="button" @click="nextStep">Continue</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Mappings -->
        <div v-if="onMappingsStep" class="mt-3 card p-0 overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b dark:bg-dark-650 dark:border-b dark:border-dark-900">
                <h2>Mappings</h2>
            </div>

            <div class="p-4">
                <form>
                    <Mappings
                        :config="config"
                        :mappings-url="mappingsUrl"
                        @updated="config = $event"
                    />

                    <div class="mt-6 flex items-center justify-center space-x-2">
                        <button class="btn" type="button" @click="prevStep">Back</button>
                        <button class="btn-primary" type="button" @click="nextStep">Run Import</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Running -->
        <div v-if="onRunningStep" class="mt-3 card overflow-hidden">
            <div class="py-12">
                <template v-if="isQueued">
                    <h3 class="text-xl font-bold text-center">{{ __('Importing...') }}</h3>
                    <p class="text-center">{{ __('The items are currently being imported. You should be able to see them shortly.') }}</p>
                </template>
                <template v-else>
                    <h3 class="text-xl font-bold text-center">{{ __('Import Complete') }} 🎉</h3>
                    <p class="text-center">{{ __('The items have been imported successfully.') }}</p>
                </template>

                <div class="mt-6 flex items-center justify-center space-x-2">
                    <button class="btn" type="button" @click="currentStep = 0">Import Again</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import HasInputOptions from '../../../vendor/statamic/cms/resources/js/components/fieldtypes/HasInputOptions.js';
import Mappings from "./Mappings.vue";

export default {
    components: {Mappings},
    mixins: [HasInputOptions],

    props: {
        mappingsUrl: String,
        collections: Array,
        taxonomies: Array,
    },

    data() {
        return {
            config: {
                type: 'csv',
                path: '/Users/duncan/Downloads/Posts-Export-2024-October-18-1511.csv',
                unique_key: null,
                destination: {
                    type: 'entries',
                    collection: 'posts',
                    taxonomy: null,
                },
                mappings: {},
            },
            currentStep: 0,
            isQueued: false,
        }
    },

    computed: {
        onConfigureStep() {
            return this.currentStep === 0;
        },

        onMappingsStep() {
            return this.currentStep === 1;
        },

        onRunningStep() {
            return this.currentStep === 2;
        },

        types() {
            return [
                { value: 'csv', label: __('CSV') },
                { value: 'xml', label: __('XML') },
            ];
        },

        destinationTypes() {
            return [
                { value: 'entries', label: __('Entries') },
                { value: 'terms', label: __('Terms') },
                { value: 'users', label: __('Users') },
            ];
        },
    },

    methods: {
        prevStep() {
            this.currentStep--;
        },

        nextStep() {
            if (this.onMappingsStep) {
                axios.post(`/cp/utilities/import`, this.config)
                    .then((response) => {
                        this.isQueued = response.data.queued;
                    })
                    .catch((error) => {
                        // TODO: Handle validation errors
                    });
            }

            this.currentStep++;
        },
    },
}
</script>
