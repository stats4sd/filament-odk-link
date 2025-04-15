<?php

namespace Stats4sd\FilamentOdkLink\Imports\XlsformTemplate;

use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

trait GetsModuleNamesPerRow
{

    /** @return XlsformModuleVersion */
    public function getModuleVersionAndNameFromRow(Collection $row, XlsformModuleVersion|XlsformTemplate $model, string $moduleColumn = 'module')
    {
        // find the moduleVersion for the current row
        if ($model instanceof XlsformModuleVersion) {
            return $model;
        }

        $moduleName = $row[$moduleColumn] ?? $model->fallback_module_name;
        return $model->xlsformModules
            ->filter(fn(XlsformModule $xlsformModule) => $xlsformModule->name === $moduleName)
            ->first()
            ->defaultXlsformVersion;


    }
}
