<script setup>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Header, Heading, Listing, DropdownItem, DocsCallout } from '@statamic/cms/ui';
import CreateImportForm from "../components/CreateImportForm.vue";

const props = defineProps({
	icon: String,
	storeUrl: String,
	fields: Array,
	initialMeta: Object,
	initialValues: Object,
	imports: Array,
});

const columns = [
	{ label: __('Name'), field: 'name' },
	{ label: __('Type'), field: 'type', width: '15%' },
	{ label: __('Destination'), field: 'destination', width: '25%' },
];
</script>

<template>
	<Head :title="__('Importer')" />

	<div class="max-w-5xl mx-auto">
		<Header :title="__('Importer')" :icon />

		<!--	<CreateImportForm-->
		<!--		class="mb-10"-->
		<!--		:action="storeUrl"-->
		<!--		:fields-->
		<!--		:initial-meta-->
		<!--		:initial-values-->
		<!--	/>-->

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