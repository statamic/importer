import CreateImportForm from "./components/CreateImportForm.vue";
import EditImportForm from "./components/EditImportForm.vue";
import ImportsListing from "./components/ImportsListing.vue";
import ImportMappingsFieldtype from "./components/Fieldtypes/ImportMappingsFieldtype.vue";

Statamic.$components.register('create-import-form', CreateImportForm);
Statamic.$components.register('edit-import-form', EditImportForm);
Statamic.$components.register('imports-listing', ImportsListing);
Statamic.$components.register('import_mappings-fieldtype', ImportMappingsFieldtype);
