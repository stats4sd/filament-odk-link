<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class Entity extends Model
{
    protected $table = 'entities';

    // e.g. for an entity created from a repeat group item, the parent entity will be the entity created from the repeat group's parent (the main form or, if it's a nested repeat group, the parent group).

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsTo<Submission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /** @return BelongsTo<Model, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('filament-odk-link.models.team_model'), 'owner_id');
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<EntityValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(EntityValue::class, 'entity_id');
    }

    /** @return BelongsToMany<DatasetVariable, $this> */
    public function datasetVariables(): BelongsToMany
    {
        return $this->belongsToMany(DatasetVariable::class, 'entity_values', 'entity_id', 'dataset_variable_name')
            ->using(EntityValue::class)
            ->withPivot('value');
    }

    /** @return Attribute<mixed, never> */
    protected function primaryKey(): Attribute
    {
        return new Attribute(
            get: fn () => $this->values()->whereHas('datasetVariable', fn (Builder $query) => $query->where('name', $this->dataset->primary_key))->first()?->value
        );
    }

    /** @return Attribute<string, string> */
    protected function label(): Attribute
    {
        return new Attribute(
            get: fn () => $this->values()->whereHas('datasetVariable', fn (Builder $query) => $query->where('name', $this->dataset->label))->first()?->value
        );
    }

    // Value Handling Functions

    /** @phpstan-param Collection<EntityValue> $entries */
    public function addValues(Collection $entries): bool
    {
        $datasetVariableNames = collect([]);

        $entries = $entries->map(function (EntityValue $entry) use (&$datasetVariableNames) {
            $entry['entity_id'] = $this->id;

            $count = 0;
            while ($datasetVariableNames->contains($entry['dataset_variable_name'])) {
                $count++;
                $entry['dataset_variable_name'] = $entry['dataset_variable_name'].".{$count}";

                if ($count > 500) {
                    throw new Exception("Error Processing Request; Too many nested values.", 1);
                }
            }

            $datasetVariableNames->push($entry['dataset_variable_name']);

            return $entry;
        });

        return $this->values()->insert($entries->toArray());
    }

    /**
     * @phpstan-param Collection<array> $entities
     *
     * @return Collection<Entity>
     */
    public function addChildEntities(Collection $entities, Dataset $dataset): Collection
    {
        // get all xlsform elements once to avoid multiple db calls when preparing select_multiple values
        $surveyRows = $this->submission->xlsform->surveyRows->load('choiceList.choiceListEntries.owner');

        return $entities->map(

            /** @phpstan-param Collection<array<string>> $values */
            function (array $values) use ($dataset, $surveyRows) {

                $entity = Entity::create([
                    'parent_id' => $this->id,
                    'dataset_id' => $dataset->id,
                    'owner_id' => $this->owner_id,
                    'submission_id' => $this->submission_id,
                ]);

                $preparedValues = collect();

                foreach ($values as $key => $value) {

                    if (! $value) {
                        continue;
                    }

                    $preparedValues->push(
                        EntityValue::make([
                            'entity_id' => $entity->id,
                            'dataset_variable_name' => $key,
                            'value' => $value,
                        ])
                    );

                    $surveyRow = $surveyRows->firstWhere('name', $key);

                    // ignore items not in the schema; e.g. "__id" fields in repeats.
                    if (! $surveyRow) {
                        continue;
                    }

                    $odkLinkService = app()->make(OdkLinkService::class);

                    $booleanValues = $odkLinkService->makeMultiSelectBooleansFromSurveyRow($entity, $surveyRow, $value);

                    $preparedValues = $preparedValues->merge($booleanValues);

                }

                $entity->addValues($preparedValues);

                return $entity;
            });
    }
}
