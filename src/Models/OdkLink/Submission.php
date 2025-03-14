<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Testing\Fluent\Concerns\Has;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;
use Stats4sd\FilamentOdkLink\Services\HelperService;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Znck\Eloquent\Relations\BelongsToThrough;

class Submission extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;
    use \Znck\Eloquent\Traits\BelongsToThrough;

    protected $table = 'submissions';

    protected $guarded = ['id'];

    protected $casts = [
        'content' => 'array',
        'errors' => 'array',
        'entries' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('owned', static function (Builder $query) {

            // if the current panel has tenancy, filter
            if ($owner = HelperService::getCurrentOwner()) {

                $query->where(function (Builder $query) use ($owner) {
                    $query->whereHas('xlsformVersion', function (Builder $query) use ($owner) {
                        $query->whereHas('xlsform', function (Builder $query) use ($owner) {
                            $query->where('owner_id', $owner->getKey());
                        });
                    });
                });
            }

            // before updating submission record
            // P.S. model event can be triggered after saving the updated submission content in modal popup,
            // but it cannot be triggered if the editing is saved in a separated Edit page
            static::updating(function ($record) {
                // submission content has been updated by user, need to delete all related entities and entity_values records,
                // because they contain values before editing
                $entities = Entity::where('submission_id', $record->id)->orderByDesc('id')->get();

                foreach ($entities as $entity) {
                    EntityValue::where('entity_id', $entity->id)->delete();
                }

                foreach ($entities as $entity) {
                    $entity->delete();
                }

                // Note: This is hard to find all related models inside a submission here,
                // it would be much easier to delete custom table records in OdkLinkService.processEntryFromSection()

                // handle the updated submission content again, this will create entities, entity_values and custom table records
                $odkLinkService = app()->make(OdkLinkService::class);
                $odkLinkService->handleUpdatedSubmissionContent($record);
            });
        });

        static::addGlobalScope('ignore_drafts', static function (Builder $query) {
            $query->where('from_draft', false);
        });
    }

    // $this->entries is an array of every Model entry created as a result of processing this submission.
    // This helper function makes it easy to update this array.
    public function addEntry(string $model, array $ids): void
    {
        $value = $this->entries;

        if ($value && array_key_exists($model, $value)) {
            $value[$model] = array_merge($value[$model], $ids);
        } else {
            $value[$model] = $ids;
        }

        $this->entries = $value;
        $this->save();
    }

    /** @return BelongsTo<XlsformVersion, $this> */
    public function xlsformVersion(): BelongsTo
    {
        return $this->belongsTo(XlsformVersion::class);
    }

    /** @return Attribute<string, never> */
    protected function xlsformTitle(): Attribute
    {
        return new Attribute(
            get: fn (): string => $this->xlsformVersion->xlsform->title,
        );
    }

    /** @return HasMany<Entity, $this> */
    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class);
    }

    /** @return HasManyThrough<EntityValue, Entity, $this> */
    public function entityValues(): HasManyThrough
    {
        return $this->hasManyThrough(EntityValue::class, Entity::class);
    }

    /** @return BelongsToThrough<Model, $this> */
    public function owner(): BelongsToThrough
    {
        return $this->belongsToThrough(
            config('filament-odk-link.models.team_model'),
            [Xlsform::class, XlsformVersion::class],
            foreignKeyLookup: [config('filament-odk-link.models.team_model') => 'owner_id']);
    }
}
