<?php

namespace Stats4sd\FilamentOdkLink\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Stats4sd\FilamentOdkLink\Models\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsFormDrafts;

/**
 * This job opens up the actual XLS file for a given Xlsform and updates the form_id and form_title fields.
 * This should be done before the file is uploaded to the ODK Aggregation service.
 */
class UpdateXlsformTitleInFile implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public WithXlsFormDrafts $xlsform)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        $filePath = $this->xlsform->xlsfile;
        $spreadsheet = IOFactory::load($filePath);

        $worksheet = $spreadsheet->getSheetByName('settings');

        if (! $worksheet) {
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
                    $formId = $this->xlsform->odk_id ?? Str::slug($this->xlsform->title);

                    // assume that the headers are on row < 10 and column < AA
                    $coordinates = str_split($coordinates);
                    $newCoordinates = $coordinates[0] . $coordinates[1] + 1;
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
                    $newCoordinates = $coordinates[0] . $coordinates[1] + 1;

                    $worksheet->setCellValue($newCoordinates, $this->xlsform->title);

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
