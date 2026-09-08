<?php

use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Models\OdkLink\DatasetVariable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\EntityValue;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;

// Entities and their values are keyed by variable *name* (entity_values has no
// dataset_variable_id column), so these tests seed variables whose ids can never
// coincide with their names — a join mistakenly comparing against ids returns
// nothing and fails loudly here.
beforeEach(function () {
    $this->team = Team::factory()->create();

    $datasetId = DB::table('datasets')->insertGetId([
        'name' => 'households',
        'owner_id' => $this->team->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach (['cows', 'goats'] as $variableName) {
        DB::table('dataset_variables')->insert([
            'dataset_id' => $datasetId,
            'name' => $variableName,
            'label' => ucfirst($variableName),
            'type' => 'integer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $entityId = DB::table('entities')->insertGetId([
        'dataset_id' => $datasetId,
        'owner_id' => $this->team->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->entity = Entity::find($entityId);
});

function addEntityValue(Entity $entity, string $variableName, string $value): void
{
    DB::table('entity_values')->insert([
        'entity_id' => $entity->id,
        'dataset_variable_name' => $variableName,
        'value' => $value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('Entity::datasetVariables joins entity_values.dataset_variable_name to dataset_variables.name', function () {
    addEntityValue($this->entity, 'cows', '4');
    addEntityValue($this->entity, 'goats', '7');

    $variables = $this->entity->datasetVariables;

    expect($variables->pluck('name')->sort()->values()->all())->toBe(['cows', 'goats'])
        ->and($variables->firstWhere('name', 'cows')->pivot->value)->toBe('4')
        ->and($variables->firstWhere('name', 'goats')->pivot->value)->toBe('7');
});

it('Entity::datasetVariables excludes variables the entity has no value for', function () {
    addEntityValue($this->entity, 'cows', '4');

    expect($this->entity->datasetVariables->pluck('name')->all())->toBe(['cows']);
});

it('DatasetVariable::values returns the entity values keyed by the variable name', function () {
    $secondEntityId = DB::table('entities')->insertGetId([
        'dataset_id' => $this->entity->dataset_id,
        'owner_id' => $this->team->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    addEntityValue($this->entity, 'cows', '4');
    addEntityValue(Entity::find($secondEntityId), 'cows', '9');
    addEntityValue($this->entity, 'goats', '7');

    $cows = DatasetVariable::firstWhere('name', 'cows');

    expect($cows->values->pluck('value')->sort()->values()->all())->toBe(['4', '9']);
});

it('addValues suffixes repeated variable names sequentially from the base name', function () {
    $this->entity->addValues(collect([
        EntityValue::make(['dataset_variable_name' => 'crop', 'value' => 'maize']),
        EntityValue::make(['dataset_variable_name' => 'crop', 'value' => 'beans']),
        EntityValue::make(['dataset_variable_name' => 'area', 'value' => '2']),
        EntityValue::make(['dataset_variable_name' => 'crop', 'value' => 'cassava']),
    ]));

    expect($this->entity->values()->pluck('value', 'dataset_variable_name')->sortKeys()->all())->toBe([
        'area' => '2',
        'crop' => 'maize',
        'crop.1' => 'beans',
        'crop.2' => 'cassava',
    ]);
});

it('addValues leaves unique variable names unchanged', function () {
    $this->entity->addValues(collect([
        EntityValue::make(['dataset_variable_name' => 'cows', 'value' => '4']),
        EntityValue::make(['dataset_variable_name' => 'goats', 'value' => '7']),
    ]));

    expect($this->entity->values()->pluck('dataset_variable_name')->sort()->values()->all())
        ->toBe(['cows', 'goats']);
});
