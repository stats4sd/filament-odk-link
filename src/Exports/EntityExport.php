<?php

namespace Stats4sd\FilamentOdkLink\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\EntityValue;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplateSection;

class EntityExport implements FromArray, WithTitle, WithHeadings
{

    public function __construct(protected Xlsform $xlsform, protected string $title, protected XlsformTemplateSection $xlsformTemplateSection)
    {
    }

    public function array(): array
    {
        $records = [];
        $dataset = $this->xlsformTemplateSection->dataset;

        // for each submission
        foreach ($this->xlsform->submissions as $submission) {

            // for entities for a particular dataset
            $entities = $submission->entities()
                ->where('dataset_id', $this->xlsformTemplateSection->dataset_id)
                ->with(['values', 'parent'])
                ->get();

            foreach ($entities as $entity) {

                // initialisation
                $record = [];


                // add parent primary key if there is a parent dataset
                if ($dataset->parent) {
                    $record[] = $entity->parent->values->where('dataset_variable_id', $dataset->parent->primary_key)->first()->value;
                }

                if ($extras = $this->getExtraVariables($entity)) {
                    foreach ($extras as $extra) {
                        $record[] = $extra;
                    }
                }

                // find value for each ODK variable
                foreach ($this->getHeadings() as $heading) {
                    $record[] = $this->getEntityValue($entity, $heading);
                }
                $records[] = $record;
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
        $headings = $this->getHeadings();

        if($extras = $this->getExtraVariableHeadings()) {
            $headings = array_merge($extras, $headings);
        }

        // add the parent-id heading to the entity-level headings as the first heading
        if ($this->xlsformTemplateSection->dataset->parent) {
            array_unshift($headings, $this->xlsformTemplateSection->dataset->parent->primary_key);
        }


        return $headings;
    }


    // get the entity-level headings.
    public function getHeadings(): array
    {
        // get all column names from schema, exclude structure item as they do not have entity_value record
        $schema = $this->xlsformTemplateSection->schema->where('type', '!=', 'structure');
        return $schema->pluck('name')->toArray();
    }

    /**
     * @param mixed $entity
     * @param mixed $heading
     * @return mixed
     */
    public function getEntityValue(mixed $entity, mixed $heading): mixed
    {
        // assume there is only one value for one ODK variable
        return $entity->values
            ->where('entity_id', $entity->id)
            ->where('dataset_variable_id', $heading)
            ->first()
            ?->value;
    }

    // overwrite this function to add extra variables to the export
    public function getExtraVariables(Entity $entity): ?array
    {
        return null;
    }

    public function getExtraVariableHeadings(): ?array
    {
        return null;
    }

}
