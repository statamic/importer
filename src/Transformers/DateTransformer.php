<?php

namespace Statamic\Importer\Transformers;

use Illuminate\Support\Carbon;
use Statamic\Fieldtypes\Date as DateFieldtype;

class DateTransformer extends AbstractTransformer
{
    public function transform(string $value): int|string
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
            return $this->config('time_seconds_enabled')
                ? DateFieldtype::DEFAULT_DATETIME_WITH_SECONDS_FORMAT
                : DateFieldtype::DEFAULT_DATETIME_FORMAT;
        }

        return DateFieldtype::DEFAULT_DATE_FORMAT;
    }
}
