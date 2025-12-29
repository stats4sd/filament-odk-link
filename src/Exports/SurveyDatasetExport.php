<?php

namespace Stats4sd\FilamentOdkLink\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;

class SurveyDatasetExport implements WithMultipleSheets
{
    protected $datasets;

    protected $entities;

    public function __construct()
    {
        // get all datasets
        $datasets = Dataset::all();

        $this->datasets = $datasets;
    }

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->datasets as $dataset) {
            // find all entities belong to this dataset
            $entities = $dataset->entities;

            // construct an excel sheet for this dataset and it's entities
            $sheets[] = new DatasetEntityExport($dataset, $entities);
        }

        return $sheets;
    }
}
