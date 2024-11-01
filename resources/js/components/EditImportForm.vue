<template>
    <div>
        <breadcrumb v-if="breadcrumbs" :url="breadcrumbs[0].url" :title="breadcrumbs[0].text" />

        <div class="flex items-center justify-between mb-6">
            <h1 v-text="title"></h1>

            <div class="flex items-center space-x-4">
                <button class="btn" :disabled="saving" @click="save()">{{ __('Save') }}</button>
                <button class="btn-primary" :disabled="saving || batchesTableMissing" @click="save(true)">{{ __('Save & Run') }}</button>
            </div>
        </div>

        <div v-if="batchesTableMissing" class="text-xs border border-yellow-dark rounded p-4 bg-yellow dark:bg-dark-blue-100 dark:border-none">
            <div class="font-bold mb-2">{{ __('Please run your migrations.') }}</div>
            <p v-html="__('importer::messages.migrations_needed')"></p>
        </div>

        <div class="mt-3 card overflow-hidden">
            <Mappings
                :config="config"
                :errors="errors"
                :mappings-url="mappingsUrl"
                @updated="config = $event"
            />
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import Mappings from "./Mappings.vue";

export default {
    components: {Mappings},

    props: {
        action: String,
        breadcrumbs: Array,
        title: String,
        initialConfig: Object,
        mappingsUrl: String,
        batchesTableMissing: Boolean,
    },

    data() {
        return {
            error: null,
            errors: {},
            saving: false,
            config: this.initialConfig,
            quickSaveKeyBinding: null,
        }
    },

    mounted() {
        this.quickSaveKeyBinding = this.$keys.bindGlobal(['mod+s'], e => {
            e.preventDefault();
            this.save();
        });
    },

    methods: {
        clearErrors() {
            this.error = null;
            this.errors = {};
        },

        save(shouldRun = false) {
            this.saving = true;
            this.clearErrors();

            axios.patch(this.action, {
                mappings: this.config.mappings,
                unique_field: this.config.unique_field,
                run: shouldRun,
            })
                .then(response => {
                    this.saving = false;
                    if (shouldRun) this.$toast.success(__('Saved & Running'));
                    if (! shouldRun) this.$toast.success(__('Saved'));
                })
                .catch(e => {
                    this.saving = false;

                    if (e.response && e.response.status === 422) {
                        const { message, errors } = e.response.data;
                        this.error = message;
                        this.errors = errors;
                        this.$toast.error(message);
                        return;
                    }

                    const message = data_get(e, 'response.data.message');
                    this.$toast.error(message || e);
                });
        },
    },
}
</script>
