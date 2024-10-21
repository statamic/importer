<?php

namespace Statamic\Importer\Commands;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\Importer\Importer;

class ImportCommand extends Command
{
    use RunsInPlease;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import stuff.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->comment('Importing...');

        $config = [
            'destination' => [
                'type' => 'entries',
                'collection' => 'posts',
            ],
            'unique_key' => 'wordpress_id',
            'mappings' => [
                'title' => [
                    'key' => 'Title',
                ],
                'slug' => [
                    'key' => 'Slug',
                ],
                'content' => [
                    'key' => 'Content',
                    'assets_base_url' => 'http://wp.dunc.lol/wp-content/uploads/',
                ],
                'author' => [
                    'key' => 'Author Email',
                    'related_field' => 'email',
                    'create_when_missing' => true,
                ],
                'date' => [
                    'key' => 'Date',
                ],
                'excerpt' => [
                    'key' => 'Excerpt',
                ],
                'featured_image' => [
                    'key' => 'Image Path',
                    'related_field' => 'path',
                    //                    'base_url' => 'http://wp.dunc.lol/wp-content/uploads/',
                ],
                'categories' => [
                    'key' => 'Categories',
                    'related_field' => 'title',
                    'create_when_missing' => true,
                ],
                'other_posts' => [
                    'key' => 'another_post',
                    'related_field' => 'wordpress_id',
                ],
                'wordpress_id' => [
                    'key' => 'id',
                ],
            ],

            //                        'destination' => [
            //                            'collection' => 'pages',
            //                        ],
            //                        'unique_key' => 'wp:post_id',
            //                        'mappings' => [
            //                            'title' => ['key' => 'title'],
            ////                            'parent' => ['key' => 'wp:post_parent'],
            ////                            'date' => ['key' => 'pubDate'],
            //                            'other_date' => ['key' => 'pubDate'],
            //                            'wordpress_id' => ['key' => 'wp:post_id'],
            //                        ],
        ];

        Importer::import($config, '/Users/duncan/Downloads/Posts-Export-2024-October-18-1511.csv');
    }
}
