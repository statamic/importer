<?php

return [
    'migrations_needed' => 'In order to keep track of import progress, the importer uses Laravel\'s Job Batching feature. It uses a <code>job_batches</code> table in your database to store information about batches. Before you can run the importer, you will need to run the <code>php artisan migrate</code> command.',
    'utility_description' => 'Import entries, taxonomies, and users from XML and CSV files.',

    'configuration_instructions' => 'You can add or modify your Blueprint fields to customize what data is imported and what fieldtype it will be stored in. You can save, refresh, and come back to this import config later until it\'s ready to run.',
    'destination_blueprint_instructions' => 'Select which blueprint should be used for imported content.',
    'destination_collection_instructions' => 'Select the collection to import entries into.',
    'destination_site_instructions' => 'Which site should the entries be imported into?',
    'destination_taxonomy_instructions' => 'Select the taxonomy to import terms into.',
    'destination_type_instructions' => 'Choose what type of data are you importing.',
    'import_file_instructions' => 'Upload a CSV or XML file to import.',
    'import_name_instructions' => 'Name this import so you can identify it later.',
    'mapping_instructions' => 'Map the fields from your import to the fields in your blueprint.',
    'strategy_instructions' => 'Choose what should happen when importing.',
    'unique_field_instructions' => 'Select a "unique field" to determine if an item already exists.',

    'assets_alt_instructions' => 'Which field should be used for the alt text?',
    'assets_base_url_instructions' => 'The base URL to prepend to the path.',
    'assets_download_when_missing_instructions' => 'If the asset can\'t be found in the asset container, should it be downloaded?',
    'assets_folder_instructions' => 'By default, downloaded assets will use same folder structure as the original URL. You can specify a different folder here.',
    'assets_related_field_instructions' => 'Which field does the data reference?',
    'assets_process_downloaded_images_instructions' => 'Should downloaded images be processed using the asset container\'s source preset?',
    'date_start_date_instructions' => 'Which field should be used for the start date?',
    'date_end_date_instructions' => 'Which field should be used for the end date?',
    'entries_create_when_missing_instructions' => 'Create the entry if it doesn\'t exist.',
    'entries_related_field_instructions' => 'Which field does the data reference?',
    'terms_create_when_missing_instructions' => 'Create the term if it doesn\'t exist.',
    'terms_related_field_instructions' => 'Which field does the data reference?',
    'toggle_format_instructions' => 'How is the value stored?',
    'toggle_values_instructions' => 'Specify the values that represent true and false in your data. You may separate multiple values with a pipe (`|`).',
    'users_create_when_missing_instructions' => 'Create the user if it doesn\'t exist.',
    'users_related_field_instructions' => 'Which field does the data reference?',

    'csv_delimiter_instructions' => 'Specify the delimiter to be used when reading the CSV file. You will need to save the import for the options to be updated.',
];
