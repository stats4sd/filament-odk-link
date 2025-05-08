<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\IsPrimaryDataSubject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
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
        'draft_data' => 'boolean',
        'test_data' => 'boolean',
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
        });

        // before updating submission record
        // P.S. model event can be triggered after saving the updated submission content in modal popup,
        // but it cannot be triggered if the editing is saved in a separated Edit page
        static::updating(function (self $submission) {

            if ($submission->isDirty('content')) {

                // submission content has been updated by user, need to delete all related entities and entity_values records,
                // because they contain values before editing
                $submission->entities()->delete();

                // Note: This is hard to find all related models inside a submission here,
                // it would be much easier to delete custom table records in OdkLinkService.processEntryFromSection()

                // handle the updated submission content again, this will create entities, entity_values and custom table records
                $odkLinkService = app()->make(OdkLinkService::class);
                $odkLinkService->handleUpdatedSubmissionContent($submission);
            }

            // if a submission is moved from test to live data, check and update the linked farm.
            if ($submission->isDirty('test_data')) {
                $subject = $submission->primaryDataSubject;
                $subject->updateCompletionStatus();
            }

        });

        static::addGlobalScope('ignore_drafts', static function (Builder $query) {
            $query->where('draft_data', false);
        });
    }

    public function scopeOnlyDraftData(Builder $query): void
    {
        $query->withoutGlobalScope('ignore_drafts')->where('draft_data', true);
    }

    public function scopeOnlyRealData(Builder $query): void
    {
        $query->where('test_data', false);
    }

    /** @return MorphTo<Model|IsPrimaryDataSubject, $this> */
    public function primaryDataSubject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model|IsPrimaryDataSubject, $this> */
    public function parent(): BelongsToThrough
    {
        return $this->belongsToThrough();
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

    /** @return BelongsToThrough<Xlsform, $this> */
    public function xlsform(): BelongsToThrough
    {
        return $this->belongsToThrough(Xlsform::class, XlsformVersion::class);
    }

    /** @return Attribute<string, never> */
    protected function xlsformTitle(): Attribute
    {
        return new Attribute(
            get: fn(): string => $this->xlsformVersion->xlsform->title,
        );
    }

    /** @return HasMany<Entity, $this> */
    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class);
    }

    /** @return HasOne<Entity, $this> */
    public function rootEntity(): HasOne
    {
        return $this->hasOne(Entity::class)
            ->where('parent_id', null);
    }

    /** @return HasManyThrough<EntityValue, Entity, $this> */
    public function entityValues(): HasManyThrough
    {
        return $this->hasManyThrough(EntityValue::class, Entity::class);
    }

    /** @return BelongsToThrough<WithXlsforms, $this> */
    public function owner(): BelongsToThrough
    {
        return $this->belongsToThrough(
            config('filament-odk-link.models.team_model'),
            [Xlsform::class, XlsformVersion::class],
            foreignKeyLookup: [config('filament-odk-link.models.team_model') => 'owner_id']);
    }

    /** @return Attribute<string, never> */
    public function odkCentralViewPageUrl(): Attribute
    {
        return new Attribute(
            get: fn() => config('filament-odk-link.odk.url') . "/#/projects/{$this->owner->odkProject->id}/forms/{$this->xlsform->odk_id}/submissions/{$this->odk_id}"
        );

    }

    /** @return Attribute<string, never> */
    protected function enketoEditUrl(): Attribute
    {

        return new Attribute(
            get: fn() => config('filament-odk-link.odk.base_endpoint') . "/projects/{$this->owner->odkProject->id}/forms/{$this->xlsform->odk_id}/submissions/{$this->odk_id}/edit",
        );
    }

    public function editOnEnketo(string $returnUrl): Redirector|RedirectResponse
    {
        $linkService = app()->make(OdkLinkService::class);
        $token = $linkService->authenticate();

        Session::put('submission_return_url', $returnUrl);


        // Prime Enketo for editing
        // We don't care about the response; only the status - but this is required to load up the form in Enketo and make sure the $enketoUrl below works.
        $response = Http::withToken($token)
            ->get($this->enketo_edit_url);
        // TODO: handle 409 response
        // TODO: handle 404 response

        $enketoUrl = config('filament-odk-link.odk.url') . '/-/edit/' . $this->xlsform->enketo_id . '?instance_id=' . $this->odk_latest_version_id . '&return_url=' . route('submission.update', ['submission' => $this]);


        $url = config('filament-odk-link.odk.url') . '/#/login?next=' . urlencode($enketoUrl);

        // Manually return the editing url
        return redirect($enketoUrl);
    }

    /** @return Attribute<?Carbon, never> */
    protected function ifUpdatedAt(): Attribute
    {
        return new Attribute(
            get: fn() => $this->updated_by ? $this->updated_at : null,
        );
    }
}
