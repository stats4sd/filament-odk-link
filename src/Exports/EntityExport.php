<?php

namespace Stats4sd\FilamentOdkLink\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\EntityValue;

class EntityExport implements FromArray, WithTitle, WithHeadings
{
    protected $xlsform;
    protected $title;
    protected $xlsformTemplateSection;

    public function __construct(Xlsform $xlsform = null, $title = null, $xlsformTemplateSection = null)
    {
        $this->xlsform = $xlsform;
        $this->title = $title;
        $this->xlsformTemplateSection = $xlsformTemplateSection;
    }

    public function array(): array
    {
        $records = [];

        // for each submission
        foreach ($this->xlsform->submissions as $submission) {

            // for entities for a particular dataset
            $entities = $submission->entities->where('dataset_id', $this->xlsformTemplateSection->dataset_id);

            foreach ($entities as $entity) {

                // initialisation
                $record = [];
                $isEmptyRecord = true;

                // find value for each ODK variable
                foreach ($this->headings() as $heading) {

                    // assume there is only one value for one ODK variable
                    $entityValue = EntityValue::select('value')->where('entity_id', $entity->id)->where('dataset_variable_id', $heading)->first();

                    if ($entityValue == null) {
                        array_push($record, null);
                    } else {
                        array_push($record, $entityValue->value);
                        $isEmptyRecord = false;
                    }

                }

                // no need to export entity record if it does not have any entity_value record
                if (!$isEmptyRecord) {
                    array_push($records, $record);
                }

            }

        }

        return $records;
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        // get all column names from schema
        // Question: should we exclude structure item, repeat group, binary, etc?
        $schema = $this->xlsformTemplateSection->schema;
        $columnNames = $schema->pluck('name')
            ->filter(fn($item) => $item['type'] !== 'structure')
            ->toArray();

        return $columnNames;
    }

}
