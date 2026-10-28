<?php

namespace Stats4sd\FilamentOdkLink\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;

class XlsformInstanceNameResolver
{
    public const DEFAULT_INSTANCE_NAME = '"instance"';

    public function resolve(Xlsform $xlsform): string
    {
        $expression = $xlsform->xlsformTemplate?->instance_name;

        if (blank($expression)) {
            return self::DEFAULT_INSTANCE_NAME;
        }

        $missingNames = $this->referencedNames($expression)
            ->diff($xlsform->surveyRows()->pluck('survey_rows.name'));

        if ($missingNames->isEmpty()) {
            return $expression;
        }

        Log::warning("Using the default instance_name for xlsform {$xlsform->id}: the template's instance_name refers to questions that are not in the form.", [
            'xlsform_id' => $xlsform->id,
            'xlsform_template_id' => $xlsform->xlsform_template_id,
            'missing_names' => $missingNames->values()->all(),
        ]);

        return self::DEFAULT_INSTANCE_NAME;
    }

    /** @return Collection<int, string> */
    private function referencedNames(string $expression): Collection
    {
        preg_match_all('/\$\{([^}]+)\}/', $expression, $matches);

        return collect($matches[1])->map(fn (string $name) => trim($name))->unique()->values();
    }
}
