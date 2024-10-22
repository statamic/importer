<template>
    <div class="mt-3 card p-0 overflow-hidden">
        <form class="p-4" @submit.prevent="save">
            <h2>{{ __('New Import') }}</h2>

            <publish-container
                name="base"
                :blueprint="blueprint"
                :values="values"
                :meta="meta"
                :errors="errors"
                :track-dirty-state="false"
                @updated="values = $event"
            >
                <div slot-scope="{ setFieldValue, setFieldMeta }">
                    <div class="-mx-6">
                        <publish-fields
                            :fields="fields"
                            @updated="setFieldValue"
                            @meta-updated="setFieldMeta"
                        />
                    </div>
                </div>
            </publish-container>

            <div class="flex justify-center">
                <button class="btn-primary" :disabled="saving">{{ __('Save & Configure') }}</button>
            </div>
        </form>
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
        action: String,
        blueprint: Object,
        fields: Array,
        initialMeta: Object,
        initialValues: Object,
    },

    data() {
        return {
            error: null,
            errors: {},
            saving: false,
            meta: this.initialMeta,
            values: this.initialValues,
        }
    },

    methods: {
        clearErrors() {
            this.error = null;
            this.errors = {};
        },

        save() {
            this.saving = true;
            this.clearErrors();

            axios.post(this.action, this.values)
                .then(response => {
                    this.saving = false;
                    window.location = response.data.redirect;
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
        }
    }
}
</script>
