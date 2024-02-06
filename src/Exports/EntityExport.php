<?php

namespace Stats4sd\FilamentOdkLink\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\EntityValue;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplateSection;

class EntityExport implements FromArray, WithTitle, WithHeadings
{

    public function __construct(protected Collection $entities, protected string $title, protected XlsformTemplateSection $xlsformTemplateSection)
    {
    }

    public function array(): array
    {

        $headings = $this->getHeadings();
        $dataset = $this->xlsformTemplateSection->dataset;

        return $this->entities
            ->map(function (Entity $entity) use ($headings, $dataset) {


                // find value for each ODK variable
                $record = $this->getEntityValues($entity, $headings);

                // adding extra variables and parent_id in reverse order (unshifting, so last thing added is the first thing in the array);
                if ($extras = $this->getExtraVariables($entity, $dataset)) {

                    // reverse order for unshifting
                    $extras = array_reverse($extras);

                    foreach ($extras as $extra) {
                        array_unshift($record, $extra);
                    }
                }

                // find value for each ODK variable
                // add parent primary key if there is a parent dataset
                if ($dataset->parent) {
                    array_unshift($record, $entity->parent->values->where('dataset_variable_id', $dataset->parent->primary_key)->first()->value);
                }

                return $record;
            })
            ->toArray();
    }

    /**
     * @return string
     */
    public
    function title(): string
    {
        return $this->title;
    }

    public
    function headings(): array
    {
        $headings = $this->getHeadings();

        if ($extras = $this->getExtraVariableHeadings($this->xlsformTemplateSection->dataset)) {
            $headings = array_merge($extras, $headings);
        }

        // add the parent-id heading to the entity-level headings as the first heading
        if ($this->xlsformTemplateSection->dataset->parent) {
            array_unshift($headings, $this->xlsformTemplateSection->dataset->parent->primary_key);
        }


        return $headings;
    }


// get the entity-level headings.
    public
    function getHeadings(): array
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
    public
    function getEntityValues(mixed $entity, array $headings): array
    {
        // assume there is only one value for one ODK variable
        return $entity->values
            ->whereIn('dataset_variable_id', $headings)
            ->map(function ($value) {
                return $value->value;
            })->toArray();
    }

// overwrite this function to add extra variables to the export
    public
    function getExtraVariables(Entity $entity, Dataset $dataset): ?array
    {
        return null;
    }

    public
    function getExtraVariableHeadings(?Dataset $dataset): ?array
    {
        return null;
    }

}
