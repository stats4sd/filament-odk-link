<?php

namespace Stats4sd\FilamentOdkLink\Imports\XlsformTemplate;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\HeadingRowImport;

class XlsformTemplateHeadingRowImport extends HeadingRowImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'survey' => $this,
            'choices' => $this,
        ];
    }
}
