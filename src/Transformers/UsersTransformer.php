<?php

namespace Statamic\Importer\Transformers;

use Statamic\Facades\User;

class UsersTransformer extends AbstractTransformer
{
    public function transform(string $value): null|string|array
    {
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
                'instructions' => __('Which field does the data reference?'),
                'default' => 'id',
                'options' => User::blueprint()
                    ->fields()
                    ->all()
                    ->map(fn ($field) => ['key' => $field->handle(), 'value' => $field->display()])
                    ->prepend(['key' => 'id', 'value' => __('ID')])
                    ->values()
                    ->all(),
            ],
            'create_when_missing' => [
                'type' => 'toggle',
                'display' => __('Create user when missing?'),
                'instructions' => __("Create the user if it doesn't exist."),
                'default' => false,
                'unless' => ['related_field' => 'name'],
            ],
        ];
    }
}
