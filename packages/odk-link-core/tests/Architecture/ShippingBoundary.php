<?php

namespace Stats4sd\FilamentOdkLink\Tests\Architecture;

class ShippingBoundary
{
    /** @return list<string> */
    public static function violations(string $source, bool $ui): array
    {
        $forbidden = ['App\\', 'Tests\\', 'Stats4sd\\FilamentOdkLink\\Tests\\', 'HaydenPierce\\ClassFinder', 'Spatie\\Permission\\'];

        if (! $ui) {
            $forbidden = [...$forbidden, 'Filament\\', 'Livewire\\', 'Stats4sd\\FilamentOdkLink\\Filament\\', 'Stats4sd\\FilamentOdkLink\\Forms\\', 'Stats4sd\\FilamentOdkLink\\Testing\\'];
        }

        $violations = [];
        $executable = '';

        foreach (token_get_all($source) as $token) {
            if (! is_array($token)) {
                $executable .= $token;

                continue;
            }

            [$type, $value] = $token;

            if (in_array($type, [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $executable .= $value;

            if (! in_array($type, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                continue;
            }

            if ($type === T_CONSTANT_ENCAPSED_STRING) {
                $quote = $value[0];
                $value = substr($value, 1, -1);
                $value = $quote === "'" ? str_replace(['\\\\', "\\'"], ['\\', "'"], $value) : stripcslashes($value);
            }

            $value = ltrim($value, '\\');

            foreach ($forbidden as $prefix) {
                if (str_starts_with($value, $prefix)) {
                    $violations[] = $value;
                }
            }

            if ($value === 'Super Admin') {
                $violations[] = $value;
            }
        }

        if (preg_match('/\$\w+\s*::\s*\$\w+\s*\(/', $executable)) {
            $violations[] = 'dynamic static submission callback';
        }

        return array_values(array_unique($violations));
    }
}
