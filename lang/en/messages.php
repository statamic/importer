<?php

return [
    'configuration_instructions' => 'You can add or modify your Blueprint fields to customize what data is imported and what fieldtype it will be stored in. You can save, refresh, and come back to this import config later until it\'s ready to run.',
    'mapping_instructions' => 'Map the fields from your import to the fields in your blueprint.',
    'migrations_needed' => 'The importer uses Laravel\'s job batching feature to keep track of the import progress, however, it requires a <code>job_batches</code> table in your database. Before you can run the importer, you will need to run <code>php artisan migrate</code>.',
    'unique_field_instructions' => 'Select a "unique field" to determine if an item already exists.',
];
