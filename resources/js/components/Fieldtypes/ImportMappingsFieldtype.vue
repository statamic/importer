<script setup>
import { Fieldtype } from '@statamic/cms';
import {
	injectPublishContext,
	Table,
	TableColumns,
	TableColumn,
	TableRows,
	TableRow,
	TableCell,
	Label,
	Badge,
	PublishContainer,
	PublishFieldsProvider,
	PublishFields,
} from '@statamic/cms/ui';

const { errors: publishErrors } = injectPublishContext();

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose, update, isReadOnly } = Fieldtype.use(emit, props);
defineExpose(expose);

const mappingUpdated = (fieldHandle, value) => update({ ...props.value, [fieldHandle]: value })

const errors = (prefix) => {
	return Object.keys(publishErrors.value || [])
		.filter(key => key.startsWith(`mappings.${prefix}`))
		.reduce((acc, key) => {
			acc[key.replace(`mappings.${prefix}.`, '')] = publishErrors.value[key];
			return acc;
		}, {});
};
</script>

<template>
	<Table>
		<TableColumns>
			<TableColumn>{{ __('Blueprint Field') }}</TableColumn>
			<TableColumn>{{ __('Data From Import') }}</TableColumn>
		</TableColumns>
		<TableRows>
			<TableRow v-for="field in meta.fields">
				<TableCell class="w-1/3">
					<Label :text="field.display" v-tooltip="{ content: field.handle, delay: 500, autoHide: false }" />
					<Badge :prepend="__('Type')" :text="field.fieldtype_title" />
				</TableCell>
				<TableCell class="w-2/3">
					<PublishContainer
						:name="`mappings.${field.handle}`"
						:model-value="value[field.handle]"
						:meta="field.meta"
						:errors="errors(field.handle)"
						:track-dirty-state="false"
						@update:model-value="mappingUpdated(field.handle, $event)"
					>
						<PublishFieldsProvider :fields="field.fields" :read-only="isReadOnly">
							<PublishFields />
						</PublishFieldsProvider>
					</PublishContainer>
				</TableCell>
			</TableRow>
		</TableRows>
	</Table>
</template>