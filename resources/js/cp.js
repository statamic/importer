import IndexPage from "./pages/Index.vue";
import CreateImportForm from "./components/CreateImportForm.vue";
import EditImportForm from "./components/EditImportForm.vue";
import BlueprintFieldtype from "./components/Fieldtypes/BlueprintFieldtype.vue";
import ImportMappingsFieldtype from "./components/Fieldtypes/ImportMappingsFieldtype.vue";

Statamic.booting(() => {
    Statamic.$inertia.register('importer::Index', IndexPage);

    Statamic.$components.register('create-import-form', CreateImportForm);
    Statamic.$components.register('edit-import-form', EditImportForm);
    Statamic.$components.register('blueprint-fieldtype', BlueprintFieldtype);
    Statamic.$components.register('import_mappings-fieldtype', ImportMappingsFieldtype);
});