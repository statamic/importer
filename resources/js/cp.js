import IndexPage from "./pages/Index.vue";
import EditPage from "./pages/Edit.vue";
import BlueprintFieldtype from "./components/Fieldtypes/BlueprintFieldtype.vue";
import ImportMappingsFieldtype from "./components/Fieldtypes/ImportMappingsFieldtype.vue";

Statamic.booting(() => {
    Statamic.$inertia.register('importer::Index', IndexPage);
    Statamic.$inertia.register('importer::Edit', EditPage);

    Statamic.$components.register('import_blueprint-fieldtype', BlueprintFieldtype);
    Statamic.$components.register('import_mappings-fieldtype', ImportMappingsFieldtype);

    Statamic.$conditions.add('destinationNeedsBlueprint', ({ container, values }) => {
        return ['entries', 'terms'].includes(values.type)
            && (values.collection?.length > 0 || values.taxonomy?.length > 0);
    });
});