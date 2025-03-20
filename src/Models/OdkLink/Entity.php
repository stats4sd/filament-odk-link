<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;

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

    /** @return MorphTo */
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


    // Value Handling Functions

    /** @phpstan-param Collection<EntityValue> $entries */
    public function addValues(Collection $entries): bool
    {
        $entries = $entries->map(function (EntityValue $entry) {
            $entry['entity_id'] = $this->id;
            return $entry;
        });

        return $this->values()->insert($entries->toArray());
    }

    /**
     * @phpstan-param Collection<array> $entities
     * @return Collection<Entity>
     */
    public function addChildEntities(Collection $entities, Dataset $dataset): Collection
    {
        return $entities->map(

        /** @phpstan-param Collection<array<string>> $values */
            function (array $values) use ($dataset) {

                $entity = Entity::create([
                    'parent_id' => $this->id,
                    'dataset_id' => $dataset->id,
                    'owner_id' => $this->owner_id,
                    'submission_id' => $this->submission_id,
                ]);

                $values = collect($values)->map(function (mixed $value, string $key) use ($entity) {
                    return EntityValue::make([
                        'entity_id' => $entity->id,
                        'dataset_variable_name' => $key,
                        'value' => $value,
                    ]);
                })
                ->filter(fn(EntityValue $value) => ! is_null($value->value));

                $entity->addValues($values);

                return $entity;
            });
    }
}
