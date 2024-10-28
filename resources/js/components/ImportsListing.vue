<template>
    <data-list :visible-columns="columns" :columns="columns" :rows="rows">
        <div class="card overflow-hidden p-0 relative" slot-scope="{ filteredRows: rows }">
            <div class="overflow-x-auto overflow-y-hidden">
                <data-list-table>
                    <template slot="cell-name" slot-scope="{ row }">
                        <a :href="row.edit_url">{{ __(row.name) }}</a>
                    </template>
                    <template slot="cell-type" slot-scope="{ value }">
                        <span class="text-xs">{{ value.toUpperCase() }}</span>
                    </template>
                    <template slot="actions" slot-scope="{ row, index }">
                        <dropdown-list>
                            <dropdown-item :text="__('Edit')" :redirect="row.edit_url" />
                            <dropdown-item
                                :text="__('Delete')"
                                class="warning"
                                @click="$refs[`deleter-${row.id}`].confirm()"
                            >
                                <resource-deleter
                                    :ref="`deleter-${row.id}`"
                                    :resource="row"
                                    :resource-title="row.name"
                                    @deleted="removeRow(row)"
                                ></resource-deleter>
                            </dropdown-item>
                        </dropdown-list>
                    </template>
                </data-list-table>
            </div>
        </div>
    </data-list>
</template>

<script>
import Listing from '../../../vendor/statamic/cms/resources/js/components/Listing.vue';

export default {
    mixins: [Listing],

    props: ['initialRows'],

    data() {
        return {
            rows: this.initialRows,
            columns: [
                { label: __('Name'), field: 'name' },
                { label: __('Type'), field: 'type', width: '15%' },
                { label: __('Destination'), field: 'destination', width: '25%' },
            ]
        }
    }
}
</script>
