<?php

namespace Statamic\Importer\WordPress;

use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fieldtypes\Bard\Augmentor as BardAugmentor;
use Statamic\Importer\Transformers\AssetsTransformer;
use Statamic\Support\Str;
use Statamic\Support\Traits\Hookable;
use Symfony\Component\DomCrawler\Crawler;

class Gutenberg
{
    use Hookable;

    public static function toBard(array $config, Blueprint $blueprint, Field $field, string $value): array
    {
        $blocks = (new GutenbergBlockParser)->parse($value);

        return collect($blocks)
            ->filter(fn ($block) => $block['blockName'])
            ->map(function (array $block) use ($config, $blueprint, $field): ?array {
                $hook = (new self)->runHooks($block['blockName'], $payload = [
                    'block' => $block,
                    'config' => $config,
                    'blueprint' => $blueprint,
                    'field' => $field,
                ]);

                if ($hook !== $payload) {
                    return $hook;
                }

                if (in_array($block['blockName'], ['core/paragraph', 'core/heading', 'core/table', 'core/verse', 'core/preformatted'])) {
                    return static::renderHtmlToProsemirror($field, $block['innerHTML']);
                }

                if ($block['blockName'] === 'core/list') {
                    $listType = isset($block['attrs']['ordered']) && $block['attrs']['ordered'] ? 'orderedList' : 'bulletList';

                    return [
                        'type' => $listType,
                        'content' => collect($block['innerBlocks'])
                            ->map(fn (array $block) => static::renderHtmlToProsemirror($field, $block['innerHTML']))
                            ->values()
                            ->all(),
                    ];
                }

                if ($block['blockName'] === 'core/quote') {
                    return [
                        'type' => 'blockquote',
                        'content' => collect($block['innerBlocks'])
                            ->filter(fn ($block) => $block['blockName'] === 'core/paragraph')
                            ->map(fn (array $block) => static::renderHtmlToProsemirror($field, $block['innerHTML']))
                            ->values()
                            ->all(),
                    ];
                }

                if ($block['blockName'] === 'core/image' && $field->get('container') && isset($config['assets_base_url'])) {
                    $assetContainer = AssetContainer::find($field->get('container'));

                    $crawler = new Crawler($block['innerHTML']);
                    $url = $crawler->filter('img')->first()->attr('src');

                    $transformer = new AssetsTransformer(
                        field: new Field('image', ['container' => $field->get('container'), 'max_files' => 1]),
                        config: [
                            'related_field' => 'url',
                            'base_url' => $config['assets_base_url'] ?? null,
                            'download_when_missing' => $config['assets_download_when_missing'] ?? false,
                        ]
                    );

                    $asset = $assetContainer->asset(path: $transformer->transform($url));

                    if (! $asset) {
                        return null;
                    }

                    return [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'image',
                                'attrs' => [
                                    'src' => $asset?->id(),
                                    'alt' => $crawler->filter('img')->first()->attr('alt'),
                                ],
                            ],
                        ],
                    ];
                }

                if ($block['blockName'] === 'core/gallery' && isset($config['assets_base_url'])) {
                    $assetContainer = $field->get('container')
                        ? AssetContainer::find($field->get('container'))
                        : AssetContainer::all()->first();

                    static::ensureBardSet($blueprint, $field, 'gallery', [
                        'display' => __('Gallery'),
                        'icon' => 'media-image-picture-gallery',
                        'fields' => [
                            ['handle' => 'images', 'field' => ['type' => 'assets', 'display' => __('Images'), 'container' => $assetContainer->handle()]],
                        ],
                    ]);

                    return [
                        'type' => 'set',
                        'attrs' => [
                            'id' => Str::random(8),
                            'values' => [
                                'type' => 'gallery',
                                'images' => collect($block['innerBlocks'])
                                    ->filter(fn ($block) => $block['blockName'] === 'core/image')
                                    ->map(function (array $block) use ($config, $field): string {
                                        $crawler = new Crawler($block['innerHTML']);
                                        $url = $crawler->filter('img')->first()->attr('src');

                                        $transformer = new AssetsTransformer(
                                            field: new Field('image', ['container' => $field->get('container'), 'max_files' => 1]),
                                            config: [
                                                'related_field' => 'url',
                                                'base_url' => $config['assets_base_url'] ?? null,
                                                'download_when_missing' => $config['assets_download_when_missing'] ?? false,
                                            ]
                                        );

                                        return $transformer->transform($url);
                                    })
                                    ->filter()
                                    ->all(),
                            ],
                        ],
                    ];
                }

                if ($block['blockName'] === 'core/html') {
                    static::ensureBardSet($blueprint, $field, 'html', [
                        'display' => __('HTML'),
                        'icon' => 'programming-script-code-brackets',
                        'fields' => [
                            ['handle' => 'html', 'field' => ['type' => 'code', 'display' => __('HTML')]],
                        ],
                    ]);

                    return [
                        'type' => 'set',
                        'attrs' => [
                            'id' => Str::random(8),
                            'values' => [
                                'type' => 'html',
                                'html' => [
                                    'code' => $block['innerHTML'],
                                    'mode' => 'htmlmixed',
                                ],
                            ],
                        ],
                    ];
                }

                if ($block['blockName'] === 'core/video') {
                    static::ensureBardSet($blueprint, $field, 'video', [
                        'display' => __('Video'),
                        'icon' => 'media-webcam-video',
                        'fields' => [
                            ['handle' => 'video', 'field' => ['type' => 'video', 'display' => __('Video')]],
                        ],
                    ]);

                    $crawler = new Crawler($block['innerHTML']);

                    // When there's no <video> element, let's embed it using the HTML set.
                    if ($crawler->filter('video')->count() === 0) {
                        static::ensureBardSet($blueprint, $field, 'html', [
                            'display' => __('HTML'),
                            'icon' => 'programming-script-code-brackets',
                            'fields' => [
                                ['handle' => 'html', 'field' => ['type' => 'code', 'display' => __('HTML')]],
                            ],
                        ]);

                        return [
                            'type' => 'set',
                            'attrs' => [
                                'id' => Str::random(8),
                                'values' => [
                                    'type' => 'html',
                                    'html' => [
                                        'code' => $block['innerHTML'],
                                        'mode' => 'htmlmixed',
                                    ],
                                ],
                            ],
                        ];
                    }

                    return [
                        'type' => 'set',
                        'attrs' => [
                            'id' => Str::random(8),
                            'values' => [
                                'type' => 'video',
                                'video' => $crawler->filter('video')->first()->attr('src'),
                            ],
                        ],
                    ];
                }

                if ($block['blockName'] === 'core/embed') {
                    if (in_array($block['attrs']['providerNameSlug'], ['youtube', 'vimeo'])) {
                        static::ensureBardSet($blueprint, $field, 'video', [
                            'display' => __('Video'),
                            'icon' => 'media-webcam-video',
                            'fields' => [
                                ['handle' => 'video', 'field' => ['type' => 'video', 'display' => __('Video')]],
                            ],
                        ]);

                        return [
                            'type' => 'set',
                            'attrs' => [
                                'id' => Str::random(8),
                                'values' => [
                                    'type' => 'video',
                                    'video' => $block['attrs']['url'],
                                ],
                            ],
                        ];
                    }

                    static::ensureBardSet($blueprint, $field, 'html', [
                        'display' => __('HTML'),
                        'icon' => 'programming-script-code-brackets',
                        'fields' => [
                            ['handle' => 'html', 'field' => ['type' => 'code', 'display' => __('HTML')]],
                        ],
                    ]);

                    return [
                        'type' => 'set',
                        'attrs' => [
                            'id' => Str::random(8),
                            'values' => [
                                'type' => 'html',
                                'html' => [
                                    'code' => $block['innerHTML'],
                                    'mode' => 'htmlmixed',
                                ],
                            ],
                        ],
                    ];
                }

                if ($block['blockName'] === 'core/code') {
                    static::ensureBardSet($blueprint, $field, 'code', [
                        'display' => __('Code'),
                        'icon' => 'programming-script-code',
                        'fields' => [
                            ['handle' => 'code', 'field' => ['type' => 'code', 'display' => __('Code')]],
                        ],
                    ]);

                    $code = Str::of($block['innerHTML'])
                        ->after('<code>')
                        ->before('</code>')
                        ->__toString();

                    return [
                        'type' => 'set',
                        'attrs' => [
                            'id' => Str::random(8),
                            'values' => [
                                'type' => 'code',
                                'code' => [
                                    'code' => $code,
                                ],
                            ],
                        ],
                    ];
                }

                if ($block['blockName'] === 'core/buttons') {
                    static::ensureBardSet($blueprint, $field, 'buttons', [
                        'display' => __('Buttons'),
                        'icon' => 'link',
                        'fields' => [
                            [
                                'handle' => 'buttons',
                                'field' => [
                                    'type' => 'grid',
                                    'mode' => 'stacked',
                                    'display' => __('Buttons'),
                                    'fields' => [
                                        ['handle' => 'label', 'field' => ['type' => 'text', 'display' => __('Label')]],
                                        ['handle' => 'url', 'field' => ['type' => 'text', 'display' => __('URL')]],
                                        ['handle' => 'open_in_new_tab', 'field' => ['type' => 'toggle', 'display' => __('Open in new tab'), 'width' => 50]],
                                        ['handle' => 'mark_as_nofollow', 'field' => ['type' => 'toggle', 'display' => __('Mark as nofollow'), 'width' => 50]],
                                    ],
                                ],
                            ],
                        ],
                    ]);

                    return [
                        'type' => 'set',
                        'attrs' => [
                            'id' => Str::random(8),
                            'values' => [
                                'type' => 'buttons',
                                'buttons' => collect($block['innerBlocks'])
                                    ->filter(fn ($block) => $block['blockName'] === 'core/button')
                                    ->map(function (array $block) {
                                        $crawler = new Crawler($block['innerHTML']);

                                        return [
                                            'id' => Str::random(8),
                                            'label' => $crawler->filter('a')->text(),
                                            'url' => $crawler->filter('a')->attr('href'),
                                            'open_in_new_tab' => $crawler->filter('a')->attr('target') === '_blank',
                                            'mark_as_nofollow' => $crawler->filter('a')->attr('rel') === 'noreferrer noopener nofollow',
                                        ];
                                    })
                                    ->values()
                                    ->all(),
                            ],
                        ],
                    ];
                }

                if ($block['blockName'] === 'core/details') {
                    static::ensureBardSet($blueprint, $field, 'details', [
                        'display' => __('Details'),
                        'icon' => 'text-formatting-initial-letter',
                        'fields' => [
                            ['handle' => 'summary', 'field' => ['type' => 'text', 'display' => __('Summary')]],
                            ['handle' => 'content', 'field' => ['type' => 'bard', 'display' => __('Content')]],
                        ],
                    ]);

                    return [
                        'type' => 'set',
                        'attrs' => [
                            'id' => Str::random(8),
                            'values' => [
                                'type' => 'details',
                                'summary' => (new Crawler($block['innerHTML']))->filter('summary')->text(),
                                'content' => collect($block['innerBlocks'])
                                    ->filter(fn ($block) => $block['blockName'] === 'core/paragraph')
                                    ->map(fn (array $block) => static::renderHtmlToProsemirror($field, $block['innerHTML']))
                                    ->values()
                                    ->all(),
                            ],
                        ],
                    ];
                }

                if ($block['blockName'] === 'core/separator') {
                    return ['type' => 'horizontalRule'];
                }

                if ($block['blockName'] === 'core/spacer') {
                    static::ensureBardSet($blueprint, $field, 'spacer', [
                        'display' => __('Spacer'),
                        'icon' => 'layout-split-horizontal',
                    ]);

                    return [
                        'type' => 'set',
                        'attrs' => [
                            'id' => Str::random(8),
                            'values' => [
                                'type' => 'spacer',
                            ],
                        ],
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    protected static function renderHtmlToProsemirror(Field $field, string $html)
    {
        return (new BardAugmentor($field->fieldtype()))->renderHtmlToProsemirror($html)['content'][0];
    }

    protected static function ensureBardSet(Blueprint $blueprint, Field $field, string $handle, array $config): void
    {
        $blueprint = BlueprintFacade::find("{$blueprint->namespace()}.{$blueprint->handle()}");
        $field = $blueprint->field($field->handle());

        $setExists = collect($field->get('sets', []))->contains(
            fn (array $section) => collect($section['sets'] ?? [])->contains(fn (array $setConfig, string $setHandle) => $setHandle === $handle)
        );

        if ($setExists) {
            return;
        }

        $blueprint
            ->ensureFieldHasConfig($field->handle(), [
                ...$field->config(),
                'sets' => array_merge($field->get('sets', []), [
                    'main' => array_merge($field->get('sets.main', []), [
                        'sets' => array_merge($field->get('sets.main.sets', []), [
                            $handle => $config,
                        ]),
                    ]),
                ]),
            ])
            ->save();
    }
}
