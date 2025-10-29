<script setup>
import { Fieldtype } from '@statamic/cms';
import { injectPublishContext, Select } from '@statamic/cms/ui';
import { computed, watch, onMounted } from 'vue';

const { values: publishValues } = injectPublishContext();

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose, update } = Fieldtype.use(emit, props);
defineExpose(expose);

const type = computed(() => publishValues.value.destination?.type);
const collection = computed(() => publishValues.value.destination?.collection[0]);
const taxonomy = computed(() => publishValues.value.destination?.taxonomy[0]);

const options = computed(() => {
	if (type.value === 'entries') {
		return props.meta.collectionBlueprints[collection.value] ?? [];
	}

	if (type.value === 'terms') {
		return props.meta.taxonomyBlueprints[taxonomy.value] ?? [];
	}

	return [];
});

watch(() => type.value, () => update(null));
watch(() => collection.value, () => update(options.value[0].handle));
watch(() => taxonomy.value, () => update(options.value[0].handle));

onMounted(() => {
	if (! props.value && type.value && (collection.value || taxonomy.value)) {
		update(options.value[0].handle);
	}
});
</script>

<template>
	<Select
		v-if="options.length > 0"
		class="w-full"
		:options
		option-label="title"
		option-value="handle"
		:model-value="value"
		@update:model-value="update($event)"
	/>
</template>