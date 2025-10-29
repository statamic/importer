<template>
    <div>
        <table class="grid-table table-auto field-mappings-table">
            <thead>
                <tr>
                    <th style="text-align: left">{{ __('Blueprint Field') }}</th>
                    <th style="text-align: left">{{ __('Data From Import') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="field in meta.fields">
                    <td class="w-1/3">
                        <label class="text-sm font-medium" :for="`mappings.${field.handle}.key`">
                            <span v-tooltip="{content: field.handle, delay: 500, autoHide: false}">{{ field.display }}</span>
                        </label>
                        <span class="badge rounded-sm text-gray-700 dark:text-dark-100 inline-flex items-center p-0 border border-gray-300 dark:border-dark-300">
                            <span class="px-1">{{ __('Type') }}</span>
                            <span class="bg-white rounded-r-sm dark:bg-dark-300 px-1">{{ field.fieldtype_title }}</span>
                        </span>
                    </td>
                    <td>
                        <publish-container
                            :name="`mappings.${field.handle}`"
                            :values="value[field.handle]"
                            :meta="field.meta"
                            :errors="errors(field.handle)"
                            :track-dirty-state="false"
                            @updated="mappingUpdated(field.handle, $event)"
                        >
                            <div slot-scope="{ setFieldValue, setFieldMeta }">
                                <div class="-mx-4 md:-ml-6">
                                    <publish-fields
                                        :fields="field.fields"
                                        :read-only="isReadOnly"
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
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';

export default {
    mixins: [Fieldtype],

    inject: [
        'storeName',
    ],

    methods: {
        mappingUpdated(fieldHandle, value) {
            this.update({
                ...this.value,
                [fieldHandle]: value,
            });
        },

        errors(prefix) {
            const state = this.$store.state.publish[this.storeName];
            if (! state) return [];

            return Object.keys(state.errors || [])
                .filter(key => key.startsWith(`mappings.${prefix}`))
                .reduce((acc, key) => {
                    acc[key.replace(`mappings.${prefix}.`, '')] = state.errors[key];
                    return acc;
                }, {});
        },
    },
}
</script>
