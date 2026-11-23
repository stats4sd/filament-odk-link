<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restructuring step 6 renamed the core namespace from Stats4sd\FilamentOdkLink\
 * to Stats4sd\OdkLink\Core\. Polymorphic type columns (and datasets.entity_model,
 * which is copied into entities.model_type) store full class names, so
 * rows written before the rename point at classes that no longer exist. This
 * rewrites the prefix in place. Host class names (e.g. App\Models\Team) are left
 * untouched. The prefixes are literals on purpose: never derive them from ::class.
 */
return new class extends Migration
{
    private const OLD_PREFIX = 'Stats4sd\\FilamentOdkLink\\';

    private const NEW_PREFIX = 'Stats4sd\\OdkLink\\Core\\';

    /** @var list<array{string, string}> [table, column] pairs that store class names */
    private const COLUMNS = [
        ['media', 'model_type'],
        ['xlsform_templates', 'owner_type'],
        ['submissions', 'primary_data_subject_type'],
        ['odk_projects', 'owner_type'],
        ['entities', 'model_type'],
        ['language_strings', 'linked_entry_type'],
        ['datasets', 'model_type'],
        ['datasets', 'entity_model'],
    ];

    public function up(): void
    {
        $this->rewrite(self::OLD_PREFIX, self::NEW_PREFIX);
    }

    public function down(): void
    {
        $this->rewrite(self::NEW_PREFIX, self::OLD_PREFIX);
    }

    private function rewrite(string $from, string $to): void
    {
        foreach (self::COLUMNS as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->whereNotNull($column)
                ->distinct()
                ->pluck($column)
                ->filter(fn (string $value) => str_starts_with($value, $from))
                ->each(fn (string $value) => DB::table($table)
                    ->where($column, $value)
                    ->update([$column => $to.substr($value, strlen($from))]));
        }
    }
};
