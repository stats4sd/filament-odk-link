<?php

use Maatwebsite\Excel\Excel as ExcelWriterType;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Stats4sd\FilamentOdkLink\Exports\XlsformTemplateTranslationsExport;

function renderTranslationsSheet(XlsformTemplateTranslationsExport $export): Worksheet
{
    $filePath = tempnam(sys_get_temp_dir(), 'translations') . '.xlsx';

    file_put_contents($filePath, Excel::raw($export, ExcelWriterType::XLSX));

    $sheet = IOFactory::load($filePath)->getActiveSheet();

    unlink($filePath);

    return $sheet;
}

it('writes a styled, protected translations sheet for a template', function () {
    $template = makeXlsformTemplate();
    addModuleVersion($template, 'intro');

    $export = new XlsformTemplateTranslationsExport($template, makeLocale('fr'));

    $sheet = renderTranslationsSheet($export);

    expect($sheet->getTitle())->toBe($export->title())
        ->and($sheet->rangeToArray('A1:F1')[0])->toBe($export->headings())
        ->and($sheet->getStyle('A1')->getFont()->getBold())->toBeTrue()
        ->and($sheet->getStyle('A1')->getFill()->getStartColor()->getRGB())->toBe('B2D23E')
        ->and($sheet->getProtection()->getSheet())->toBeTrue();
});
