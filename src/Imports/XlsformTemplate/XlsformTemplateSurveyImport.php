<?php

namespace Stats4sd\FilamentOdkLink\Imports\XlsformTemplate;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class XlsformTemplateSurveyImport implements ToModel, WithHeadingRow, WithUpserts, SkipsEmptyRows, WithChunkReading, ShouldQueue
{
    use RemembersRowNumber;

    public function __construct(public XlsformModuleVersion $xlsformModuleVersion, public Collection $translatableHeadings)
    {
    }

    public function model(array $row): ?SurveyRow
    {

        $row = collect($row);

        // skip entries not part of the current module
        if($row['module'] !== $this->xlsformModuleVersion->xlsformModule->name) {
            return null;
        }


        // get the columns that are part of the XLSform spec (but are not translatable columns like 'label')
        $data = $row
            ->filter(fn($value, $key) => in_array($key, $this->getSurveyRowHeaders()))
            ->filter(fn($value, $key) => $value !== null);

        // all non-xlsform-spec and non-translatable columns are considered properties and bundled as json.
        $props = $row
            ->filter(fn($value, $key) => !$this->translatableHeadings->contains($key))
            ->filter(fn($value, $key) => !in_array($key, $this->getSurveyRowHeaders()))
            ->filter(fn($value, $Key) => $value !== null);

        $data['row_number'] = $this->getRowNumber(); // make sure ordering from file is preserved even when it's changed since the first upload
        $data['properties'] = $props;

        // if we are importing an entire template, the "template" id and type should be based on the $row['module'] import
        // TODO: fix these confusing relation names!

        $data['xlsform_module_version_id'] = $this->xlsformModuleVersion->id;
        $data['updated_during_import'] = true; // to make sure we don't delete this row after import.

        // for end_group or end_repeats, the name might be empty.
        // In that case, we generate a unique name based on the type.
        if (!isset($data['name']) || $data['name'] === '') {
            $data['name'] = $data['type'] . '_' . $this->getRowNumber();
        }

        // check if this is a custom module import, adjust survey row name
        // ( CUSTOM TO HOLPA )
        // TODO: refactor this into a more generalised approach.
        if ($this->xlsformModuleVersion->name === 'custom') {
            $form = $this->xlsformModuleVersion->xlsform;
            $team_name = strtolower(str_replace(' ', '_', $form->owner->name));
            $data['name'] = $team_name . '_' . $this->xlsformModuleVersion->xlsformModule->id . '_' . $data['name'];
        }

        // check 'required' is a bool
        if (isset($data['required'])) {
            $data['required'] = match (strtolower($data['required'])) {
                'true', 'yes', '1' => 1,
                default => 0,
            };

        }

        return new SurveyRow($data->toArray());

    }

    private function getSurveyRowHeaders(): array
    {
        return
            [
                'name',
                'type',
                'required',
                'relevant',
                'appearance',
                'calculation',
                'constraint',
                'choice_filter',
                'repeat_count',
                'default',
                'note',
                'trigger',
            ];
    }

    public function uniqueBy(): array
    {
        return ['xlsform_module_version_id', 'name', 'type'];
    }

    public function isEmptyWhen(array $row): bool
    {
        return (!isset($row['name']) || $row['name'] === '')
            && (!isset($row['type']) || $row['type'] === '');
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
