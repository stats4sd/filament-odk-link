<?php

namespace Stats4sd\FilamentOdkLink\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\EntityValue;

class EntityExport implements FromArray, WithTitle, WithHeadings
{
    protected $xlsform;
    protected $submissionIds;
    protected $title;
    protected $xlsformTemplateSection;

    public function __construct(Xlsform $xlsform = null, $submissionIds = null, $title = null, $xlsformTemplateSection = null)
    {
        $this->xlsform = $xlsform;
        $this->submissionIds = $submissionIds;
        $this->title = $title;

        // Assumption:
        // 1. Xlsform template must have a root section, which is defined as a non repeat group
        // 2. There is one and only one section is not repeat group for a xlsform template, all other sections are defined for repeat group
        // $this->xlsformTemplateSection = $this->xlsform->xlsformTemplate->xlsformTemplateSections->firstWhere('is_repeat', 0);
        $this->xlsformTemplateSection = $xlsformTemplateSection;
    }

    public function array(): array
    {
        $records = [];

        // for each submission
        foreach ($this->submissionIds as $submissionId) {
            // find entity for root
            $rootEntity = Entity::where('submission_id', $submissionId)->where('dataset_id', $this->xlsformTemplateSection->dataset_id)->first();
            $record = [];

            // find value for each ODK variable
            foreach ($this->headings() as $heading) {
                // dump($heading);
                $entityValue = EntityValue::select('value')->where('entity_id', $rootEntity->id)->where('dataset_variable_id', $heading)->first();
                
                if ($entityValue == null) {
                    // add null to record
                    // dump('add null to array');
                    array_push($record, null);
                } else {
                    // dump($entityValue->value);
                    array_push($record, $entityValue->value);
                }

            }

            // dump($record);
            array_push($records, $record);

        }

        dump($records);
       
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
        // TODO: should we exclude structure item, repeat group, binary, etc?
        $schema = $this->xlsformTemplateSection->schema;
        $columnNames = $schema->pluck('name')->toArray();

        // dump($schema);
        // dump($columnNames);

        return $columnNames;
    }

}
