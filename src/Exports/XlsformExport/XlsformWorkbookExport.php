<?php

namespace Stats4sd\FilamentOdkLink\Exports\XlsformExport;

use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;


class XlsformWorkbookExport implements WithMultipleSheets, ShouldQueue
{

    public function __construct(public Xlsform $xlsform)
    {
    }

    public function sheets(): array
    {
        $sheets = [
            new XlsformSurveyExport($this->xlsform),
            new XlsformChoicesExport($this->xlsform),
            new XlsformSettingsExport($this->xlsform),
        ];

        if ($this->xlsform->xlsformTemplate->templateEntityLists()->exists()) {
            $sheets[] = new XlsformEntitiesExport($this->xlsform);
        }

        return $sheets;
    }
}
