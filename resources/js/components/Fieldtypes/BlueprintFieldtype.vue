<template>
    <v-select
        searchable
        :options="options"
        :get-option-label="(option) => option.title"
        :get-option-key="(option) => option.handle"
        :value="value"
        :reduce="opt => opt.handle"
        @input="update($event)"
    />
</template>

<script>
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';

export default {
    mixins: [Fieldtype],

    inject: ['storeName'],

    mounted() {
        if (! this.value && this.type && (this.collection || this.taxonomy)) {
            this.$emit('input', this.options[0].handle);
        }
    },

    computed: {
        type() {
            return this.$store.state.publish[this.storeName].values.destination.type;
        },

        collection() {
            return this.$store.state.publish[this.storeName].values.destination.collection[0];
        },

        taxonomy() {
            return this.$store.state.publish[this.storeName].values.destination.taxonomy[0];
        },

        options() {
            if (this.type === 'entries') {
                return this.meta.collectionBlueprints[this.collection];
            }

            if (this.type === 'terms') {
                return this.meta.taxonomyBlueprints[this.taxonomy];
            }
        },
    },

    watch: {
        type() {
            this.update(null);
        },

        collection() {
            this.update(this.options[0].handle);
        },

        taxonomy() {
            this.update(this.options[0].handle);
        },
    }
}
</script>
