import IndexPage from "./pages/Index.vue";
import EditPage from "./pages/Edit.vue";
import BlueprintFieldtype from "./components/Fieldtypes/BlueprintFieldtype.vue";
import ImportMappingsFieldtype from "./components/Fieldtypes/ImportMappingsFieldtype.vue";

Statamic.booting(() => {
    Statamic.$inertia.register('importer::Index', IndexPage);
    Statamic.$inertia.register('importer::Edit', EditPage);

    Statamic.$components.register('import_blueprint-fieldtype', BlueprintFieldtype);
    Statamic.$components.register('import_mappings-fieldtype', ImportMappingsFieldtype);
});