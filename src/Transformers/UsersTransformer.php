<?php

namespace Statamic\Importer\Transformers;

use Statamic\Facades\User;
use Statamic\Support\Str;

class UsersTransformer extends AbstractTransformer
{
    public function transform(string $value): null|string|array
    {
        // When $value is a JSON string, decode it.
        if (Str::startsWith($value, ['{', '[']) || Str::startsWith($value, ['[', ']'])) {
            $value = collect(json_decode($value, true))->join('|');
        }

        if ($this->config('related_field') === 'id') {
            if (is_string($value)) {
                return explode('|', $value);
            }

            return $value;
        }

        $users = collect(explode('|', $value))->map(function ($value) {
            $user = User::query()
                ->where($this->config('related_field'), $value)
                ->first();

            if (
                ! $user
                && $this->config('related_field') !== 'name'
                && $this->config('create_when_missing')
            ) {
                $user = User::make();

                if ($this->config('related_field') === 'email') {
                    $user->email($value);
                } else {
                    $user->set($this->config('related_field'), $value);
                }

                $user->save();

                return $user->id();
            }

            return $user?->id();
        })->filter();

        return $this->field->get('max_items') === 1 ? $users->first() : $users->all();
    }

    public function fieldItems(): array
    {
        return [
            'related_field' => [
                'type' => 'select',
                'display' => __('Related Field'),
                'instructions' => __('importer::messages.users_related_field_instructions'),
                'default' => 'id',
                'options' => User::blueprint()
                    ->fields()
                    ->all()
                    ->map(fn ($field) => ['key' => $field->handle(), 'value' => $field->display()])
                    ->prepend(['key' => 'id', 'value' => __('ID')])
                    ->values()
                    ->all(),
                'validate' => 'required',
            ],
            'create_when_missing' => [
                'type' => 'toggle',
                'display' => __('Create user when missing?'),
                'instructions' => __("importer::messages.users_create_when_missing_instructions"),
                'default' => false,
                'unless' => ['related_field' => 'name'],
            ],
        ];
    }
}
