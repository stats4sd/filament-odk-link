<?php

namespace Stats4sd\FilamentOdkLink\Exports;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\RequiredMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Abstracts\HasXlsformDrafts;
use Stats4sd\FilamentOdkLink\Exports\XlsformExport\ExportsXlsformContent;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\EntityValue;

class DatasetAsMediaAttachmentExport implements FromCollection, WithHeadings
{
    use ExportsXlsformContent;

    /** @var Collection<Locale> */
    public Collection $locales;

    /** @var \Illuminate\Support\Collection<string> */
    public \Illuminate\Support\Collection $propertyHeadings;

    /** @var Collection<Entity> */
    public Collection $datasetEntities;

    public function __construct(public HasXlsformDrafts $xlsform, public Dataset $dataset)
    {
        $this->datasetEntities = $this->dataset->entities;
        $this->locales = $xlsform->owner->locales;
        $this->propertyHeadings = $this->dataset->entities->map(function (Entity $entity) {
            return $entity
            ->values
            ->filter(fn(EntityValue $value) => $value->dataset_variable_name !== 'uuid')
            ->pluck('dataset_variable_name');
        })->flatten()->unique();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection(): \Illuminate\Support\Collection
    {
        return $this->datasetEntities
            ->map(function (Entity $entity) {
                return [
                    'entity_id' => $entity->id,
                    'entity_uuid' => $entity->uuid,
                    'name' => $entity->primary_key,
                    'label' => $entity->label,
                    ...$this->getOtherEntityProperties($entity)->toArray(),
                ];
            });
    }

    public function getOtherEntityProperties(Entity $entity): SupportCollection
    {
        $entityValues = $entity
        ->values
        // ->filter(fn(EntityValue $value) => $value->dataset_variable_name !== $entity->dataset->primary_key)
        // ->filter(fn(EntityValue $value) => $value->dataset_variable_name !== $entity->dataset->label)
        ->filter(fn(EntityValue $value) => $value->dataset_variable_name !== 'uuid')
        ->mapWithKeys(function ($value) {
            return [$value->dataset_variable_name => $value->value];
        });

        ray($entityValues);

        return $this->propertyHeadings->mapWithKeys(function(string $heading) use ($entityValues) {
            return [$heading => $entityValues[$heading] ?? null];
        });
    }


    public function headings(): array
    {
        return [
            'entity_id',
            'entity_uuid',
            'name',
            'label',
            ...$this->propertyHeadings->toArray(),
        ];
    }
}
