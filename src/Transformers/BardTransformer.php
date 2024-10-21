<?php

namespace Statamic\Importer\Transformers;

use Statamic\Facades\AssetContainer;
use Statamic\Fieldtypes\Bard\Augmentor as BardAugmentor;
use Statamic\Importer\WordPress\Gutenberg;
use Statamic\Support\Str;

class BardTransformer extends AbstractTransformer
{
    public function transform(string $value): array
    {
        $this->enableBardButtons();

        if ($this->isGutenbergValue($value)) {
            return Gutenberg::toBard(
                config: $this->config,
                blueprint: $this->blueprint,
                field: $this->field,
                value: $value
            );
        }

        return (new BardAugmentor($this->field->fieldtype()))->renderHtmlToProsemirror($value)['content'];
    }

    private function enableBardButtons(): void
    {
        $this->blueprint->ensureFieldHasConfig(
            handle: $this->field->handle(),
            config: array_merge($this->field->config(), [
                'container' => $this->field->get('container') ?? AssetContainer::all()->first()?->handle(),
                'buttons' => [
                    'h1',
                    'h2',
                    'h3',
                    'bold',
                    'italic',
                    'unorderedlist',
                    'orderedlist',
                    'removeformat',
                    'quote',
                    'anchor',
                    'image',
                    'table',
                    'horizontalrule',
                    'codeblock',
                ],
            ])
        );

        $this->blueprint->save();
    }

    private function isGutenbergValue(string $value): bool
    {
        return Str::contains($value, '<!-- wp:');
    }
}
