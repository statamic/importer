<?php

namespace Statamic\Importer\Transformers;

use Illuminate\Support\Carbon;
use Statamic\Fieldtypes\Date as DateFieldtype;
use Statamic\Importer\Sources\Csv;
use Statamic\Importer\Sources\Xml;
use Statamic\Support\Arr;
use Statamic\Support\Str;

class DateTransformer extends AbstractTransformer
{
    public function transform(string $value): array|int|string
    {
        if ($this->field->get('mode') === 'range') {
            return [
                'start' => $this->transformDate($value),
                'end' => $this->transformDate(Arr::get($this->values, $this->config('end'))),
            ];
        }

        return $this->transformDate($value);
    }

    private function transformDate(string $value): int|string
    {
        $date = Carbon::parse($value);

        $saveFormat = $this->field->get('format', $this->defaultFormat());

        $formatted = $date->format($saveFormat);

        if (is_numeric($formatted)) {
            $formatted = (int) $formatted;
        }

        return $formatted;
    }

    private function defaultFormat()
    {
        if ($this->field->get('time_enabled') && $this->field->get('mode', 'single') === 'single') {
            return $this->field->get('time_seconds_enabled')
                ? DateFieldtype::DEFAULT_DATETIME_WITH_SECONDS_FORMAT
                : DateFieldtype::DEFAULT_DATETIME_FORMAT;
        }

        return DateFieldtype::DEFAULT_DATE_FORMAT;
    }

    public function fieldItems(): array
    {
        if ($this->field->get('mode') === 'range') {
            $row = match ($this->import?->get('type')) {
                'csv' => (new Csv($this->import))->getItems($this->import->get('path'))->first(),
                'xml' => (new Xml($this->import))->getItems($this->import->get('path'))->first(),
            };

            // To prevent the field mapping from being filtered out, the Start Date is the "normal" key,
            // and the End Date is a config option.
            return [
                'key' => [
                    'type' => 'select',
                    'display' => __('Start'),
                    'instructions' => __('importer::messages.date_start_date_instructions'),
                    'options' => collect($row)->map(fn ($value, $key) => [
                        'key' => $key,
                        'value' => "<{$key}>: ".Str::truncate($value, 200),
                    ])->values()->all(),
                ],
                'end' => [
                    'type' => 'select',
                    'display' => __('End'),
                    'instructions' => __('importer::messages.date_end_date_instructions'),
                    'options' => collect($row)->map(fn ($value, $key) => [
                        'key' => $key,
                        'value' => "<{$key}>: ".Str::truncate($value, 200),
                    ])->values()->all(),
                ],
            ];
        }

        return [];
    }
}
