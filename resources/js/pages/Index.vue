<script setup>
import { Head, Link, router } from '@statamic/cms/inertia';
import {
	Header,
	Panel,
	PanelFooter,
	Card,
	PublishContainer,
	PublishFieldsProvider,
	PublishFields,
	Button,
	Heading,
	Listing,
	DropdownItem,
	DocsCallout
} from '@statamic/cms/ui';
import { Pipeline, Request, PipelineStopped } from '@statamic/cms/save-pipeline';
import { useTemplateRef, ref } from 'vue';

const props = defineProps({
	icon: String,
	storeUrl: String,
	fields: Array,
	initialMeta: Object,
	initialValues: Object,
	imports: Array,
});

const container = useTemplateRef('container');
const meta = ref(props.initialMeta);
const values = ref(props.initialValues);
const saving = ref(false);
const errors = ref({});

const columns = [
	{ label: __('Name'), field: 'name' },
	{ label: __('Type'), field: 'type', width: '15%' },
	{ label: __('Destination'), field: 'destination', width: '25%' },
];

const save = () => {
	saving.value = true;
	errors.value = {};

	new Pipeline()
		.provide({ container, errors, saving })
		.through([
			new Request(props.storeUrl, 'post'),
		])
		.then((response) => {
			router.get(response.data.redirect);
		})
		.catch((e) => {
			if (!(e instanceof PipelineStopped)) {
				Statamic.$toast.error(__('Something went wrong'));
				console.error(e);
			}
		});
};
</script>

<style>
.importer .group-fieldtype {
	padding: 0!important;
}

.importer .group-fieldtype .divide-y {
	padding: 0!important;
}
</style>

<template>
	<Head :title="__('Importer')" />

	<div class="importer max-w-5xl mx-auto">
		<Header :title="__('Importer')" :icon />

		<Panel :heading="__('Create a New Import')">
			<Card inset>
				<PublishContainer
					ref="container"
					:blueprint="fields"
					:errors="errors"
					:track-dirty-state="false"
					:meta="meta"
					v-model="values"
					as-config
				>
					<PublishFieldsProvider :fields="fields">
						<PublishFields />
					</PublishFieldsProvider>
				</PublishContainer>
			</Card>
			<PanelFooter>
				<Button :text="__('Save & Continue')" variant="primary" :disabled="saving" @click="save" />
			</PanelFooter>
		</Panel>

		<template v-if="imports.length > 0">
			<Heading class="mt-12 mb-4" :text="__('Recent Imports')" />

			<Listing
				:items="imports"
				:columns
				:allow-search="false"
				:allow-presets="false"
				:allow-customizing-columns="false"
			>
				<template #cell-name="{ row }">
					<Link :href="row.edit_url" v-text="row.name" />

					<resource-deleter
						:ref="`deleter-${row.id}`"
						:resource="row"
						:resource-title="row.name"
						@deleted="() => router.reload({ only: ['imports'] })"
					></resource-deleter>
				</template>
				<template #cell-type="{ value }">
					<span class="text-xs">{{ value.toUpperCase() }}</span>
				</template>
				<template #prepended-row-actions="{ row }">
					<DropdownItem :text="__('Edit')" :href="row.edit_url" />
					<DropdownItem :text="__('Delete')" variant="destructive" @click="$refs[`deleter-${row.id}`].confirm()" />
				</template>
			</Listing>
		</template>

		<DocsCallout :topic="__('Importer')" url="https://statamic.com/addons/statamic/importer/docs" />
	</div>
</template>