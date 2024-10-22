<template>
    <div>
        <div v-if="loading" class="p-6 text-center">
            <loading-graphic  />
        </div>

        <div v-else class="flex flex-col gap-6">
            <table class="grid-table table-auto">
                <thead>
                <tr>
                    <th style="text-align: left">Field</th>
                    <th style="text-align: left">Element</th>
                </tr>
                </thead>
                <tbody>
                    <tr v-for="field in fields">
                        <td class="w-96">
                            <label class="text-base">{{ field.display }}</label>
                        </td>
                        <td class="flex flex-col">
                            <select-input
                                :name="`mappings.${field.handle}.key`"
                                v-model="mappings[field.handle]['key']"
                                :options="itemOptions"
                            />

                            <!-- TODO: These config fields should probably come from the transformer, rather than being hard-coded here. -->
                            <div class="mt-4 flex flex-col gap-2" v-if="field.type === 'assets'">
                                <div>
                                    <label class="font-semibold text-sm mb-1" :for="`mappings.${field.handle}.related_field`">{{ __('Related Field') }}</label>
                                    <div class="help-block">
                                        <p>Which field does the data reference?</p>
                                    </div>
                                    <select-input
                                        :name="`mappings.${field.handle}.related_field`"
                                        v-model="mappings[field.handle]['related_field']"
                                        :options="[
                                            { value: 'url', label: __('URL') },
                                            { value: 'path', label: __('Path') },
                                        ]"
                                        required
                                    />
                                </div>

                                <div v-if="mappings[field.handle]['related_field'] === 'url'">
                                    <label class="font-semibold text-sm mb-1" :for="`mappings.${field.handle}.base_url`">{{ __('Base URL') }}</label>
                                    <div class="help-block">
                                        <p>Please specify the part of the URL that's not part of an asset container path. (eg. `https://domain.com/wp-content/uploads`)</p>
                                    </div>
                                    <input
                                        type="url"
                                        :id="`mappings.${field.handle}.base_url`"
                                        :name="`mappings.${field.handle}.base_url`"
                                        v-model="mappings[field.handle]['base_url']"
                                        class="font-mono input-text"
                                    />
                                </div>

                                <div class="flex gap-2">
                                    <input
                                        type="checkbox"
                                        :id="`mappings.${field.handle}.download_when_missing`"
                                        :name="`mappings.${field.handle}.download_when_missing`"
                                        v-model="mappings[field.handle]['download_when_missing']"
                                    />
                                    <label :for="`mappings.${field.handle}.download_when_missing`">{{ __('Download when missing?') }}</label>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-col gap-2" v-if="field.type === 'bard' && field.config.container">
                                <div>
                                    <label class="font-semibold text-sm mb-1" :for="`mappings.${field.handle}.assets_base_url`">{{ __('Assets Base URL') }}</label>
                                    <div class="help-block">
                                        <p>Please specify the part of the URL that's not part of an asset container path. (eg. `https://domain.com/wp-content/uploads`)</p>
                                    </div>
                                    <input
                                        type="url"
                                        :id="`mappings.${field.handle}.assets_base_url`"
                                        :name="`mappings.${field.handle}.assets_base_url`"
                                        v-model="mappings[field.handle]['assets_base_url']"
                                        class="font-mono input-text"
                                        required
                                    />
                                </div>

                                <div class="flex gap-2">
                                    <input
                                        type="checkbox"
                                        :id="`mappings.${field.handle}.assets_download_when_missing`"
                                        :name="`mappings.${field.handle}.assets_download_when_missing`"
                                        v-model="mappings[field.handle]['assets_download_when_missing']"
                                    />
                                    <label :for="`mappings.${field.handle}.assets_download_when_missing`">{{ __('Download assets when missing?') }}</label>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-col gap-2" v-if="field.type === 'entries' || field.type === 'terms' || field.type === 'users'">
                                <div>
                                    <label class="font-semibold text-sm mb-1" :for="`mappings.${field.handle}.related_field`">{{ __('Related Field') }}</label>
                                    <div class="help-block">
                                        <p>Which field does the data reference?</p>
                                    </div>
                                    <input
                                        type="text"
                                        :id="`mappings.${field.handle}.related_field`"
                                        :name="`mappings.${field.handle}.related_field`"
                                        v-model="mappings[field.handle]['related_field']"
                                        class="font-mono input-text"
                                        placeholder="slug"
                                        required
                                    />
                                </div>

                                <div class="flex gap-2">
                                    <input
                                        type="checkbox"
                                        :id="`mappings.${field.handle}.create_when_missing`"
                                        :name="`mappings.${field.handle}.create_when_missing`"
                                        v-model="mappings[field.handle]['create_when_missing']"
                                    />
                                    <label :for="`mappings.${field.handle}.create_when_missing`">{{ __('Create when missing?') }}</label>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div>
                <label class="font-semibold text-sm mb-1">{{ __('Unique Identifier field') }}</label>
                <div class="help-block mb-2">
                    <p>{{ __('Please select a "unique identifier field". The importer will use this to determine if an entry already exists.') }}</p>
                </div>

                <div v-for="field in uniqueKeys" class="flex items-center space-x-2 mb-1">
                    <input type="radio" :id="`unique_key_${field}`" name="unique_key" :value="field" v-model="uniqueKey">
                    <label :for="`unique_key_${field}`">{{ field }}</label>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";

export default {
    props: {
        config: Object,
        mappingsUrl: String,
    },

    data() {
        return {
            fields: null,
            uniqueKeys: null,
            itemOptions: null,
            mappings: this.config.mappings,
            uniqueKey: this.config.unique_key,
            loading: true,
        }
    },

    mounted() {
        this.getMappings();
    },

    methods: {
        getMappings() {
            axios.post(this.mappingsUrl, this.config)
                .then((response) => {
                    this.fields = response.data.fields;
                    this.uniqueKeys = response.data.unique_keys;
                    this.itemOptions = response.data.item_options;

                    this.mappings = this.fields.reduce((acc, field) => {
                        acc[field.handle] = {
                            key: this.config.mappings[field.handle] ? this.config.mappings[field.handle].key : null,
                        };

                        return acc;
                    }, {});

                    this.loading = false;
                })
                .catch((error) => {
                    // TODO: Handle validation errors...
                    console.log('something happened', error.response.data);
                });
        },
    },

    watch: {
        mappings: {
            handler() {
                this.$emit('updated', {
                    ...this.config,
                    mappings: this.mappings,
                });
            },
            deep: true,
        },

        uniqueKey() {
            this.$emit('updated', {
                ...this.config,
                unique_key: this.uniqueKey,
            });
        },
    },
}
</script>
