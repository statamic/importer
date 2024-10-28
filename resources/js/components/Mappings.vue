<template>
    <div>
        <div v-if="loading" class="p-6 text-center">
            <loading-graphic  />
        </div>

        <div v-else class="flex flex-col gap-6">
            <div class="content max-w-lg">
                <h2>{{ __('Configuration') }}</h2>
                <p>{{ __('You can add or modify your Blueprint fields to customize what data is imported and what fieldtype it will be stored in. You can save, refresh, and come back to this import config later until it\'s ready to run.') }}</p>
            </div>
            <div>
                <label class="font-semibold text-sm mb-1">{{ __('Field Mappings') }}</label>
                <div class="help-block">
                    <p>{{ __('Map the fields from your import to the fields in your blueprint.') }}</p>
                </div>
            </div>
            <table class="grid-table table-auto field-mappings-table">
                <thead>
                    <tr>
                        <th style="text-align: left">{{ __('Blueprint Field') }}</th>
                        <th style="text-align: left">{{ __('Data From Import') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="field in fields">
                        <td class="w-1/3">
                            <label class="text-sm font-medium" :for="`mappings.${field.handle}.key`">
                                {{ field.display }}
                            </label>
                            <span class="badge rounded-sm text-gray-700 dark:text-dark-100 inline-flex items-center p-0 border border-gray-300 dark:border-dark-300">
                                <span class="px-1">{{ __('Type') }}</span>
                                <span class="bg-white rounded-r-sm dark:bg-dark-300 px-1">{{ field.fieldtype_title }}</span>
                            </span>
                        </td>
                        <td>
                            <publish-container
                                :name="`mappings-${field.handle}`"
                                :values="mappings[field.handle]"
                                :meta="field.meta"
                                :errors="errors"
                                :track-dirty-state="false"
                                @updated="mappings[field.handle] = $event"
                            >
                                <div slot-scope="{ setFieldValue, setFieldMeta }">
                                    <div class="-mx-4 md:-ml-6">
                                        <publish-fields
                                            :fields="field.fields"
                                            @updated="setFieldValue"
                                            @meta-updated="setFieldMeta"
                                        />
                                    </div>
                                </div>
                            </publish-container>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="errors?.hasOwnProperty('mappings')">
                <small class="help-block text-red-500 mt-2 mb-0" v-for="(error, i) in errors.mappings" :key="i" v-text="error" />
            </div>

            <div>
                <label class="font-semibold text-sm mb-1">{{ __('Unique Field') }}</label>
                <div class="help-block mb-2">
                    <p>{{ __('Select a "unique field" to determine if an item already exists.') }}</p>
                </div>

                <div v-for="field in availableUniqueKeys" class="flex items-center space-x-2 space-y-1 mb-1">
                    <input type="radio" :id="`unique_field_${field.handle}`" name="unique_field" :value="field.handle" v-model="uniqueKey">
                    <label :for="`unique_field_${field.handle}`" class="mt-0">{{ field.display }}</label>
                </div>

                <div v-if="errors?.hasOwnProperty('unique_field')">
                    <small class="help-block text-red-500 mt-2 mb-0" v-for="(error, i) in errors.unique_field" :key="i" v-text="error" />
                </div>
            </div>
        </div>
    </div>
</template>

<style>
.field-mappings-table .form-group {
    padding-top: 16px;
    padding-bottom: 16px;
}

.field-mappings-table .form-group:first-child {
    padding-top: 0;
}

.field-mappings-table .form-group:last-child {
    padding-bottom: 0;
}
</style>

<script>
import axios from "axios";

export default {
    props: {
        config: Object,
        errors: Object,
        mappingsUrl: String,
    },

    data() {
        return {
            fields: null,
            uniqueKeys: null,
            mappings: {},
            uniqueKey: this.config.unique_field,
            loading: true,
        }
    },

    computed: {
        availableUniqueKeys() {
            return this.uniqueKeys.filter((field) => {
                return this.mappings[field.handle]?.key !== null;
            });
        },
    },

    mounted() {
        this.getMappings();
    },

    methods: {
        getMappings() {
            axios.post(this.mappingsUrl, this.config)
                .then((response) => {
                    this.fields = response.data.fields;
                    this.uniqueKeys = response.data.unique_fields;

                    this.mappings = this.fields.reduce((acc, field) => {
                        acc[field.handle] = field.values ?? {};
                        return acc;
                    }, {});

                    this.loading = false;
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
                unique_field: this.uniqueKey,
            });
        },
    },
}
</script>
