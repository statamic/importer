<template>
    <v-select
        :options="options"
        :get-option-label="(option) => option.title"
        :get-option-key="(option) => option.handle"
        :value="value"
        :searchable="false"
        :reduce="opt => opt.handle"
        @input="update($event)"
    />
</template>

<script>
export default {
    mixins: [Fieldtype],

    inject: ['storeName'],

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
            this.$emit('input', null);
        },

        collection() {
            this.$emit('input', this.options[0].handle);
        },

        taxonomy() {
            this.$emit('input', this.options[0].handle);
        },
    }
}
</script>
