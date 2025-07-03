<?php

namespace Stats4sd\FilamentOdkLink\Imports\XlsformTemplate;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class XlsformModuleImport implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithMultipleSheets
{
    use Importable;

    public function __construct(public XlsformTemplate $xlsformTemplate, public string $moduleColumn = 'module') {}

    public function sheets(): array
    {
        return [
            'survey' => $this,
        ];
    }

    public function collection(Collection $collection): void
    {
        // for any rows that do not have a module set... add 'generic' modules for them.
        // Any gaps in module labelling gets a new generic module. E.g. for these rows
        // module | type | name...
        // mod-1  | text | qu1...
        // mod-1  | text | qu2...
        //        | text | qu3...
        //        | text | qu4...
        // mod-2  | text | qu5...
        //        | text | qu6...
        // mod-3  | text | qu7...

        // In this case, qu3 and qu4 get put into a generic module; and qu6 is put into a separate generic module. This lets us preserve the ordering of the questions while also keeping the user-defined module breaks.
        //
        // If there are no modules defined at all, the entire form is put into a single generic module.

        $count = 1;
        $genericModuleName = $this->xlsformTemplate->title.' - Unspecified Module '.$count;

        $collection = $collection
            ->map(function ($row) use (&$genericModuleName, &$count) {
                if (! isset($row['module'])) {
                    $row['module'] = $genericModuleName;
                } else {

                    // if there's a module specified, then we are done with the nth 'unspecified' module, and next time we need one we'll start n+1th unspecified module
                    $count++;
                    $genericModuleName = $this->xlsformTemplate->title.' - Unspecified Module '.$count;
                }

                return $row;
            });

        // now all entries have a module, process them:

        $order = 1;
        $collection
            ->groupBy('module')
            ->each(function ($row, $key) use (&$order) {

                $canBeExtended = false;
                $canBeReplaced = false;

                if (isset($row[0]['localisable_module'])) {
                    $canBeExtended = $row[0]['localisable_module'] === 'extend';
                    $canBeReplaced = $row[0]['localisable_module'] === 'replace';
                }

                // temporarily store the survey row unique name/type combos so we can match the module to the generic survey row later on in the import
                $rowNames = collect($row)->map(function ($rowEntry) {
                    return $rowEntry['type'].'_'.$rowEntry['name'];
                });

                // make sure xlsformModule exists
                /** @var XlsformModule $module */
                $module = $this->xlsformTemplate->xlsformModules()->updateOrCreate([
                    'name' => $key,
                ], [
                    'label' => $key,
                    'default_order' => $order,
                    'can_be_extended' => $canBeExtended,
                    'can_be_replaced' => $canBeReplaced,
                    'row_names' => $rowNames,
                ]);

                // if the module can be extended, it means we need to leave space for a 'localised_$name' module directly after it, so increment $order by 2.
                if ($module->can_be_extended) {
                    $order++;
                }

                $order++;
            });
    }
}
