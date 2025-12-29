<?php

namespace Stats4sd\FilamentOdkLink\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplateSection;

class DatasetEntityExport implements FromArray, WithHeadings, WithTitle
{
    // by default, assume there is no corresponding xlsformTemplateSection found for the dataset
    private int $isXlsformTemplateSectionFound = 0;

    private XlsformTemplateSection $xlsformTemplateSection;

    public function __construct(protected Dataset $dataset, protected Collection $entities) {
        if ($dataset->xlsformTemplateSections->count() > 0) {
            $this->isXlsformTemplateSectionFound = 1;

            // use the first xlsformTemplateSection
            //
            // Question: how to determine which xlsformTemplateSection is the correct one when there are multiple xlsformTemplateSection using the same dataset?
            // e.g. xlsformTemplateSection "seasonal_workers_s" and "seasonal_labourers_s"
            $this->xlsformTemplateSection = $dataset->xlsformTemplateSections[0];
        }
    }

    public function array(): array
    {
        $headings = $this->getHeadings();

        $dataset = $this->dataset;

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
                    // add checking to check if first record can be found
                    array_unshift($record, $entity->parent->values->where('dataset_variable_name', $dataset->parent->primary_key)->first()?->value);
                }

                return $record;
            })
            ->toArray();
    }

    public function title(): string
    {
        // use dataset name as excel sheet name
        return $this->dataset->name;
    }

    public function headings(): array
    {
        $headings = $this->getHeadings();

        if ($extras = $this->getExtraVariableHeadings($this->dataset)) {
            $headings = array_merge($extras, $headings);
        }

        // add the parent-id heading to the entity-level headings as the first heading
        // add checking as a xlsformTemplateSection may not relate to a dataset
        if ($this->dataset?->parent) {
            array_unshift($headings, $this->dataset->parent->primary_key);
        }


        return $headings;
    }

    // get the entity-level headings
    public function getHeadings(): array
    {
        // Note: we extract data per dataset
        // we use dataset's first xlsformTemplateSections as xlsformTemplateSection, then we get variable names from xlsformTemplateSection's schema
        
        // Question:
        // Is there any other way to get variable names without accessing xlsformTemplateSection?

        // if there is no corresponding xlsformTemplateSection found, there should be no data stored in entities and entity_values tables.
        // Therefore, there should be no column headers
        if ($this->isXlsformTemplateSectionFound == 0) {
            return [];
        }
        else
        {
            // get all column names from schema, exclude structure item as they do not have entity_value record
            $schema = $this->xlsformTemplateSection->schema->where('type', '!=', 'structure');

            // TO FIX: entity values are not mapped to headers correspondingly
            return $schema->pluck('name')->toArray();
        }
    }

    public function getEntityValues(mixed $entity, array $headings): array
    {
        // assume there is only one value for one ODK variable
        return $entity->values
            ->whereIn('dataset_variable_name', $headings)
            ->map(function ($value) {
                return $value->value;
            })->toArray();
    }

    // overwrite this function to add extra variables to the export
    public function getExtraVariables(Entity $entity, Dataset $dataset): ?array
    {
        return null;
    }

    public function getExtraVariableHeadings(?Dataset $dataset): ?array
    {
        return null;
    }
}
