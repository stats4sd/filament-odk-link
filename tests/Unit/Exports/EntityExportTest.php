<?php

use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Exports\EntityExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\EntityValue;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplateSection;

function makeEntityWithValues(array $values): Entity
{
    $entity = new Entity;
    $entity->setRelation('values', collect($values)->map(fn (array $value) => new EntityValue($value)));

    return $entity;
}

it('getEntityValues keys entity values by dataset_variable_name', function () {
    $export = new EntityExport(new Collection, 'Test', new XlsformTemplateSection);

    $entity = makeEntityWithValues([
        ['dataset_variable_name' => 'cows', 'value' => '4'],
        ['dataset_variable_name' => 'goats', 'value' => '7'],
    ]);

    expect($export->getEntityValues($entity, ['cows', 'goats']))->toBe(['4', '7']);
});

it('getEntityValues projects onto headings positionally so a missing value does not shift later columns', function () {
    $export = new EntityExport(new Collection, 'Test', new XlsformTemplateSection);

    $entity = makeEntityWithValues([
        ['dataset_variable_name' => 'cows', 'value' => '4'],
        ['dataset_variable_name' => 'chickens', 'value' => '9'],
    ]);

    expect($export->getEntityValues($entity, ['cows', 'goats', 'chickens']))->toBe(['4', null, '9']);
});

it('getEntityValues preserves a numeric-zero value', function () {
    $export = new EntityExport(new Collection, 'Test', new XlsformTemplateSection);

    $entity = makeEntityWithValues([
        ['dataset_variable_name' => 'cows', 'value' => '0'],
    ]);

    expect($export->getEntityValues($entity, ['cows']))->toBe(['0']);
});
