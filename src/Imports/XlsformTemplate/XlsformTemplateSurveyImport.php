<?php

namespace Stats4sd\FilamentOdkLink\Imports\XlsformTemplate;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Services\HelperService;

class XlsformTemplateSurveyImport implements ShouldQueue, SkipsEmptyRows, ToModel, WithChunkReading, WithHeadingRow, WithUpserts
{
    use RemembersRowNumber;

    public function __construct(public XlsformModuleVersion $xlsformModuleVersion, public Collection $translatableHeadings, public string $moduleColumn = 'module')
    {
    }

    public function model(array $row): ?SurveyRow
    {

        /** @var Collection<int|string, int | mixed | null> $row */
        $row = collect($row);

        // skip entries not part of the current module
        $moduleName = $this->xlsformModuleVersion->xlsformModule->name ?? $this->xlsformModuleVersion->name;
        if ($row[$this->moduleColumn] !== $moduleName) {
            return null;
        }

        // get the columns that are part of the XLSform spec (but are not translatable columns like 'label')
        /** @var Collection<int|string, int | mixed | null> $data */
        $data = $row
            ->filter(fn($value, $key) => in_array($key, $this->getSurveyRowHeaders()))
            ->filter();

        // all non-xlsform-spec and non-translatable columns are considered properties and bundled as json.
        $props = $row
            ->filter(fn($value, $key) => !$this->translatableHeadings->contains($key))
            ->filter(fn($value, $key) => !in_array($key, $this->getSurveyRowHeaders()))
            ->filter();

        $data['row_number'] = $this->getRowNumber(); // make sure ordering from file is preserved even when it's changed since the first upload
        $data['properties'] = $props;

        $data['xlsform_module_version_id'] = $this->xlsformModuleVersion->id;
        $data['updated_during_import'] = true; // to make sure we don't delete this row after import.

        // find the choice list for select questions
        if (Str::contains($row['type'], ['select_one', 'select_multiple'])) {
            $choiceListName = collect(explode(' ', $row['type']))->last();
            $data['choice_list_id'] = $this->xlsformModuleVersion->choiceLists()->where('list_name', $choiceListName)->first()->id;
        }

        // for end_group or end_repeats, the name might be empty.
        // In that case, we generate a unique name based on the type.
        if (!isset($data['name']) || $data['name'] == '') {
            $data['name'] = $data['type'] . '_' . $this->getRowNumber();
        }

        // check if this is a custom module import, adjust survey row name
        // ( CUSTOM TO HOLPA )
        // TODO: refactor this into a more generalised approach.
        if ($this->xlsformModuleVersion->name === 'custom' && $owner = HelperService::getCurrentOwner()) {
            $form = $this->xlsformModuleVersion
                ->xlsforms
                ->filter(fn(Xlsform $xlsform) => $xlsform->owner_id === $owner->getKey())
                ->first();

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
        return 1000;
    }
}
