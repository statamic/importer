<script setup>
import { Head } from '@statamic/cms/inertia';
import { Header, Button, Alert, PublishContainer } from '@statamic/cms/ui';
import { Pipeline, Request, PipelineStopped } from '@statamic/cms/save-pipeline';
import { useTemplateRef, ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
	icon: String,
	action: String,
	initialTitle: String,
	initialBlueprint: Object,
	initialValues: Object,
	initialMeta: Object,
	batchesTableMissing: Boolean,
});

const container = useTemplateRef('container');
const title = ref(props.initialTitle);
const blueprint = ref(props.initialBlueprint);
const values = ref(props.initialValues);
const meta = ref(props.initialMeta);
const saving = ref(false);
const errors = ref({});

const save = (shouldRun = false) => {
	saving.value = true;
	errors.value = {};

	new Pipeline()
		.provide({ container, errors, saving })
		.through([
			new Request(props.action, 'patch', {
				_run: shouldRun,
			}),
		])
		.then((response) => {
			title.value = response.data.data.name;
			Statamic.$dirty.remove(container.value.name);
			Statamic.$toast.success(__('Saved'));
		})
		.catch((e) => {
			if (!(e instanceof PipelineStopped)) {
				Statamic.$toast.error(__('Something went wrong'));
				console.error(e);
			}
		});
};

let saveKeyBinding;

onMounted(() => {
	saveKeyBinding = Statamic.$keys.bindGlobal(['mod+s'], (e) => {
		e.preventDefault();
		save();
	});
});

onUnmounted(() => saveKeyBinding.destroy());
</script>

<template>
	<Head :title="title" />

	<div class="max-w-5xl mx-auto">
		<Header :title="title" :icon>
			<Button :disabled="saving" :text="__('Save')" @click="save(false)" />
			<Button :disabled="saving || batchesTableMissing" variant="primary" :text="__('Save & Run')" @click="save(true)" />
		</Header>

		<Alert
			v-if="batchesTableMissing"
			class="mb-8"
			variant="warning"
			:heading="__('Please run your migrations!')"
			:text="__('importer::messages.migrations_needed')"
		/>

		<PublishContainer
			ref="container"
			name="import"
			:blueprint
			:errors
			:meta="meta"
			v-model="values"
			as-config
		/>
	</div>
</template>