<script setup>
import axios from 'axios';
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
import { ref } from 'vue';

const props = defineProps({
	icon: String,
	storeUrl: String,
	fields: Array,
	initialMeta: Object,
	initialValues: Object,
	imports: Array,
});

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

	axios.post(props.storeUrl, values.value)
		.then(response => {
			saving.value = false;
			router.get(response.data.redirect);
		})
		.catch(error => {
			saving.value = false;

			if (error.response && error.response.status === 422) {
				errors.value = error.response.data.errors;
				Statamic.$toast.error(error.response.data.message);

				return;
			}

			Statamic.$toast.error(error.response.data.message || error);
		});
};
</script>

<template>
	<Head :title="__('Importer')" />

	<div class="max-w-5xl mx-auto">
		<Header :title="__('Importer')" :icon />

		<Panel :heading="__('Create a New Import')">
			<Card>
				<PublishContainer
					:blueprint="fields"
					:errors="errors"
					:track-dirty-state="false"
					:meta="meta"
					v-model="values"
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
			<Heading class="mb-4" :text="__('Recent Imports')" />

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