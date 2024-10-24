<template>
    <div class="card p-6">
        <form @submit.prevent="save">
            <h2>{{ __('Create a New Import') }}</h2>

            <publish-container
                name="base"
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

            <div class="mt-6">
                <button class="btn-primary" :disabled="saving">{{ __('Save & Continue') }}</button>
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
