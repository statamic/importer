<?php

return [
    'configuration_instructions' => 'You can add or modify your Blueprint fields to customize what data is imported and what fieldtype it will be stored in. You can save, refresh, and come back to this import config later until it\'s ready to run.',
    'mapping_instructions' => 'Map the fields from your import to the fields in your blueprint.',
    'migrations_needed' => 'In order to keep track of import progress, the importer uses Laravel\'s Job Batching feature. It uses a <code>job_batches</code> table in your database to store information about batches. Before you can run the importer, you will need to run the <code>php artisan migrate</code> command.',
    'unique_field_instructions' => 'Select a "unique field" to determine if an item already exists.',
];
