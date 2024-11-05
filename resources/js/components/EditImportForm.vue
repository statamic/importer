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

        <publish-container
            v-if="fieldset"
            ref="container"
            :name="publishContainer"
            :blueprint="fieldset"
            :values="values"
            :reference="initialReference"
            :meta="meta"
            :errors="errors"
            :track-dirty-state="trackDirtyState"
            @updated="values = $event"
        >
            <div slot-scope="{ container, setFieldValue, setFieldMeta }">
                <publish-tabs
                    :enable-sidebar="false"
                    @updated="setFieldValue"
                    @meta-updated="setFieldMeta"
                    @focus="container.$emit('focus', $event)"
                    @blur="container.$emit('blur', $event)"
                ></publish-tabs>
            </div>
        </publish-container>
    </div>
</template>

<script>
import HasHiddenFields from '../../../vendor/statamic/cms/resources/js/components/publish/HasHiddenFields';

export default {
    mixins: [HasHiddenFields],

    props: {
        publishContainer: String,
        initialFieldset: Object,
        initialValues: Object,
        initialMeta: Object,
        initialTitle: String,
        action: String,
        method: String,
        breadcrumbs: Array,
        batchesTableMissing: Boolean,
    },

    data() {
        return {
            fieldset: _.clone(this.initialFieldset),
            values: _.clone(this.initialValues),
            meta: _.clone(this.initialMeta),
            error: null,
            errors: {},
            title: this.initialTitle,
            saving: false,
            quickSaveKeyBinding: null,
            trackDirtyState: true,
        }
    },

    computed: {
        hasErrors() {
            return this.error || Object.keys(this.errors).length;
        },

        isDirty() {
            return this.$dirty.has(this.publishContainer);
        },
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

            setTimeout(() => {
                this.$refs.container.saving();
                this.performSaveRequest(shouldRun);
            }, 151); // 150ms is the debounce time for fieldtype updates
        },

        performSaveRequest(shouldRun = false) {
            const payload = _.clone(this.values);

            if (shouldRun) {
                payload['run'] = true;
            }

            this.$axios[this.method](this.action, payload).then(response => {
                this.saving = false;
                if (! response.data.saved) {
                    return this.$toast.error(__(`Couldn't save import`));
                }
                this.title = response.data.data.name;
                this.$refs.container.saved();
                this.$toast.success(__('Saved'));
                clearTimeout(this.trackDirtyStateTimeout);
                this.trackDirtyState = false;
                this.meta = response.data.data.meta;
                this.values = this.resetValuesFromResponse(response.data.data.values);
                this.trackDirtyStateTimeout = setTimeout(() => (this.trackDirtyState = true), 500);
                this.$nextTick(() => this.$emit('saved', response));
            }).catch(error => this.handleAxiosError(error));
        },

        handleAxiosError(e) {
            this.saving = false;
            if (e.response && e.response.status === 422) {
                const { message, errors } = e.response.data;
                this.error = message;
                this.errors = errors;
                this.$toast.error(message);
                this.$reveal.invalid();
            } else if (e.response) {
                this.$toast.error(e.response.data.message);
            } else {
                this.$toast.error(e || 'Something went wrong');
            }
        },
    },
}
</script>
