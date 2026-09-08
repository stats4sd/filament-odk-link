<?php

namespace Stats4sd\FilamentOdkLink\Imports\XlsformTemplate;

use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

trait GetsModuleNamesPerRow
{
    /** @return XlsformModuleVersion */
    public function getModuleVersionAndNameFromRow(Collection $row, XlsformModuleVersion | XlsformTemplate $model, string $moduleColumn = 'module')
    {
        // find the moduleVersion for the current row
        if ($model instanceof XlsformModuleVersion) {
            return $model;
        }

        $moduleName = $row[$moduleColumn] ?? null;

        // If the module name is set in the form, use it to find the module
        if ($moduleName) {
            return $model->xlsformModules
                ->filter(fn (XlsformModule $xlsformModule) => $xlsformModule->name === $moduleName)
                ->first()
                ->defaultXlsformVersion;
        }

        // Otherwise, find the 'generic' modules for the template and match based on the
        // modules.row_name field

        return $model->xlsformModules
            ->filter(function (XlsformModule $xlsformModule) use ($row) {

                $moduleRows = $xlsformModule->row_names;

                $rowName = $row['type'] . '_' . $row['name'];

                return $moduleRows->contains($rowName);

            })
            ->first()
            ->defaultXlsformVersion;

    }
}
