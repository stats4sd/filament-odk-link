<?php

namespace Stats4sd\FilamentOdkLink\Imports\XlsformTemplate;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\RequiredMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class XlsformTemplateChoiceListImport implements ShouldQueue, SkipsEmptyRows, ToModel, WithChunkReading, WithHeadingRow, WithMultipleSheets, WithUpserts
{
    use GetsModuleNamesPerRow;
    use Importable;

    /**
     * @throws \Exception
     */
    public function __construct(public XlsformTemplate|XlsformModuleVersion $model, public string $moduleColumn = 'module')
    {
        if ($model instanceof XlsformTemplate) {
            $model->load('xlsformModules.xlsformModuleVersions.choiceLists');
        }
    }

    public function sheets(): array
    {
        return [
            'survey' => $this,
        ];
    }

    public function model(array $row): ?ChoiceList
    {
        $row = collect($row);

        // skip non-select questions
        if (! Str::startsWith(trim($row['type']), 'select_')) {
            return null;
        }


        // skip select_from_file questions
        if (Str::contains(trim($row['type']), '_from_file')) {

            // TODO: refactor this - this should be in a more logical place to handle RequiredMedia.


            if ($this->model instanceof XlsformTemplate) {
                $xlsformTemplate = $this->model;
            } else {
                $xlsformTemplate = $this->model->xlsformModule->xlsformTemplate;
            }

            RequiredMedia::updateOrCreate([
                'name' => Str::of($row['type'])->trim()->afterLast(' '),
                'xlsform_template_id' => $xlsformTemplate->id,
            ], [
                'links_to_dataset' => true,
            ]);

            return null;
        }


        // get current module
        $moduleVersion = $this->getModuleVersionAndNameFromRow($row, $this->model, $this->moduleColumn);

        $listName = Str::of($row['type'])->trim()->afterLast(' ')->toString();

        if (isset($row['localisable'])) {
            $localisable = match ($row['localisable']) {
                'true', 'yes', 'TRUE', 'YES', 'Yes', 'True', '1', 1, true => true,
                default => false,
            };
        } else {
            $localisable = false;
        }

        return new ChoiceList([
            'xlsform_module_version_id' => $moduleVersion->id,
            'list_name' => $listName,
            'is_localisable' => $localisable,
        ]);

    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function isEmptyWhen(array $row): bool
    {
        return ! isset($row['type']) || $row['type'] === '';
    }

    public function uniqueBy(): array
    {
        return ['xlsform_module_version_id', 'list_name'];
    }

    public function batchSize(): int
    {
        return 1000;
    }
}
