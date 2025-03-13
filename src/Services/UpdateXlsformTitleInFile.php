<?php

namespace Stats4sd\FilamentOdkLink\Services;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;


class UpdateXlsformTitleInFile
{

    /**
     * @throws Exception
     */
    public static function process(Xlsform|XlsformTemplate $xlsform): void
    {

        $filePath = $xlsform->xlsfile;

        ray($filePath);
        ray($xlsform->xlsfile);
        ray($xlsform);
        dd($xlsform);

        $spreadsheet = IOFactory::load($filePath);

        $worksheet = $spreadsheet->getSheetByName('settings');

        if (!$worksheet) {
            abort(500, 'There is no settings sheet for this XLS Form');
        }

        $titleUpdated = false;
        $idUpdated = false;

        // find the `form_id` entry and update:
        foreach ($worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();

            $cellIterator->setIterateOnlyExistingCells(true);

            foreach ($cellIterator as $cell) {
                if ($cell->getValue() === 'form_id' || $cell->getValue() === 'id_string') {

                    $coordinates = $cell->getCoordinate();

                    // if the form is already deployed, we must use the existing form_id on ODK:
                    $formId = $xlsform->odk_id ?? Str::slug($xlsform->title);

                    // assume that the headers are on row < 10 and column < AA
                    $coordinates = str_split($coordinates);
                    $newCoordinates = $coordinates[0] . ((int)$coordinates[1] + 1);
                    $worksheet->setCellValue($newCoordinates, $formId);
                    $idUpdated = true;
                    if ($titleUpdated) {
                        break;
                    }
                }

                if ($cell->getValue() === 'form_title') {

                    $coordinates = $cell->getCoordinate();

                    // assume that the headers are on row < 10 and column < AA
                    $coordinates = str_split($coordinates);
                    $newCoordinates = $coordinates[0] . ((int)$coordinates[1] + 1);

                    $worksheet->setCellValue($newCoordinates, $xlsform->title);

                    $titleUpdated = true;
                    if ($idUpdated) {
                        break;
                    }
                }
            }

            if ($titleUpdated) {
                break;
            }
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($filePath);

    }
}
