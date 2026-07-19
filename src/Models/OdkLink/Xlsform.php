<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\Client\RequestException;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Stats4sd\FilamentOdkLink\Events\XlsformWasPublished;
use Stats4sd\FilamentOdkLink\Exports\XlsformExport\XlsformWorkbookExport;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\DeployDraftXlsformToOdkCentral;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\NotifyUserThatXlsformFileIsDeployedAsDraft;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\NotifyUserThatXlsformFileIsUpdated;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\PublishXlsformOnOdkCentral;
use Stats4sd\FilamentOdkLink\Events\XlsformDraftWasDeployed;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\UpdateXlsformFile;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Abstracts\HasXlsformDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Services\HelperService;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class Xlsform extends HasXlsformDrafts implements HasMedia
{
    use HasRelationships;

    protected $table = 'xlsforms';

    protected $casts = [
        'schema' => 'collection',
        'odk_draft_updated_at' => 'timestamp',
        'odk_published_at' => 'timestamp',
        'has_locales' => 'boolean',
    ];

    protected $guarded = [];

    // ******************* SETUP ***********************************
    protected static function booted(): void
    {
        // when the model is created;
        static::saved(static function (self $xlsform) {

            if (!$xlsform->has_latest_template) {
                $xlsform->syncWithTemplate();
            }

            // check if the needs_up date was updated from true to false
            if ($xlsform->wasChanged('draft_needs_update') && !$xlsform->draft_needs_update) {

                // if only draft was deployed
                if ($xlsform->live_needs_update) {
                    XlsformDraftWasDeployed::dispatch($xlsform->id);
                }
            }

            if ($xlsform->wasChanged('live_needs_update') && !$xlsform->live_needs_update) {
                // notify user that the form file has been updated
                XlsformWasPublished::dispatch($xlsform->id);

            }

        });

        static::created(static function (self $xlsform) {
            $xlsform->setup();
        });

        static::deleting(static function (self $xlsform) {
            $odkLinkService = app()->make(OdkLinkService::class);
            $xlsform->deleteFromOdkCentral($odkLinkService);
        });

        static::addGlobalScope('owned', static function (Builder $query) {

            // if the current panel has tenancy, filter
            if ($owner = HelperService::getCurrentOwner()) {

                $query->where(function (Builder $query) use ($owner) {
                    $query->where('owner_id', $owner->getKey());
                });

            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('xlsform_file')
            ->singleFile()
            ->useDisk(config('filament-odk-link.storage.xlsforms'));

        $this->addMediaCollection('attached_media')
            ->useDisk(config('filament-odk-link.storage.xlsforms'));
    }

    // ****************** COMPUTED ATTRIBUTES ************************

    // Get an xlsformId string that is both human-readable and guaranteed to be unique within the platform
    /** @return Attribute<string, never> */
    protected function xlsformId(): Attribute
    {
        return new Attribute(
            get: fn(): string => str($this->title)->slug() . '_' . $this->id,
        );
    }

    /** @return Attribute<string, never> */
    protected function currentVersion(): Attribute
    {
        return new Attribute(
            get: fn(): string => $this->xlsformVersions()->latest()->first()->version ?? '',
        );
    }

    /** @return Attribute<string, never> */
    protected function status(): Attribute
    {
        return new Attribute(
            get: function (): string {

                if ($this->processing) {
                    return 'PROCESSING';
                }

                if (!$this->odk_draft_token) {
                    return 'NOT DEPLOYED';
                }

                if ($this->is_active) {
                    return 'LIVE';
                }

                if ($this->xlsformVersions()->where('is_draft', false)->count() === 0) {
                    return 'DRAFT READY FOR TESTING';
                }

                return 'INACTIVE';
            },
        );
    }

    // ****************** RELATIONSHIPS ************************

    /** @return BelongsTo<XlsformTemplate, $this> */
    public function xlsformTemplate(): BelongsTo
    {
        return $this->belongsTo(XlsformTemplate::class);
    }

    /** @return HasMany<XlsformVersion, $this> */
    public function xlsformVersions(): HasMany
    {
        return $this->hasMany(XlsformVersion::class);
    }

    /** @return HasOne<XlsformVersion, $this> */
    public function xlsformDraftVersion(): HasOne
    {
        return $this->hasOne(XlsformVersion::class)->where('is_draft', true);
    }

    /** @return HasManyThrough<Submission, XlsformVersion, $this> */
    public function submissions(): HasManyThrough
    {
        return $this->hasManyThrough(Submission::class, XlsformVersion::class);
    }

    /** @return BelongsTomany<Locale, $this> */
    public function locales(): BelongsToMany
    {
        return $this->belongsToMany(Locale::class);
    }

    /** @return Attribute<Collection<Locale>, never> */
    protected function localeList(): Attribute
    {
        // Check if this form is marked as having a custom list of locales. If not, defer to the owner's locales
        return new Attribute(
            get: function () {
                if ($this->has_locales) {
                    $locales = $this->load('locales')->locales;

                    // if this is the first time the form is created; locales may be null
                    if ($locales->count() === 0) {
                        $locales = $this->xlsformTemplate->locales;
                        $this->locales()->sync($locales);
                    }
                } else {

                    $locales = $this->owner->locales;
                }

                return $locales;

            },
        );
    }

    // ***** RELATIONSHIPS VIA XLSFORM TEMPLATE *****

    /** @return HasMany<RequiredMedia, XlsformTemplate> */
    public function requiredMedia(): HasMany
    {
        return $this->xlsformTemplate->requiredMedia();
    }

    public function requiredFixedMedia(): HasMany
    {
        return $this->xlsformTemplate->requiredFixedMedia();
    }

    public function requiredDataMedia(): HasMany
    {
        return $this->xlsformTemplate->requiredDataMedia();
    }

    /** @return HasMany<RequiredMedia, XlsformTemplate> */
    public function attachedFixedMedia(): HasMany
    {
        return $this->xlsformTemplate->attachedFixedMedia();
    }

    /** @return HasMany<RequiredMedia, XlsformTemplate> */
    public function attachedDataMedia(): HasMany
    {
        return $this->xlsformTemplate->attachedDataMedia();
    }

    // *********************** METHODS ****************************

    public function getOdkLinkAttribute(): ?string
    {
        $appends = !$this->is_active ? '/draft' : '';

        return config('filament-odk-link.odk.url') . '/#/projects/' . $this->owner->odkProject->id . '/forms/' . $this->odk_id . $appends;
    }

    // make sure the xlsform is using the latest template
    public function syncWithTemplate(): void
    {

        // check through the template modules; If this form is missing any, add the default version
        $this->xlsformTemplate->xlsformModules
            ->sortBy('default_order')

            // check for modules where the module version is not _already_ linked to this form (to avoid resetting custom ordering)
            ->filter(fn(XlsformModule $module) => $this->xlsformModuleVersions->doesntContain('xlsform_module_id', $module->id))
            ->each(function (XlsformModule $xlsformModule) {
                $this->xlsformModuleVersions()->attach($xlsformModule->defaultXlsformVersion, ['order' => $xlsformModule->default_order]);

                // If the XlsformModule `can_be_extended` add a 'local' version of the module immediately after it
                if ($xlsformModule->can_be_extended) {
                    $localModuleVersion = XlsformModuleVersion::firstOrCreate([
                        'owner_id' => $this->owner->id,
                        'name' => 'Local ' . $xlsformModule->name,
                    ]);

                    $this->xlsformModuleVersions()->sync([$localModuleVersion->id => ['order' => $xlsformModule->default_order + 1]], detaching: false);
                }
            });

        // reload the pivot so the swap below sees any versions just attached
        $this->load('xlsformModuleVersions');
        $this->localiseModules();

        $this->has_latest_template = true;
        $this->saveQuietly();
    }

    public function localiseModules(): void
    {
       // `can_be_replaced` modules: if the form is still using the global default,
        // swap it for the team's own local version in the global version's place.
        $this->xlsformTemplate->xlsformModules
            ->filter(fn(XlsformModule $module) => $module->can_be_replaced)
            ->each(function (XlsformModule $xlsformModule) {

                $currentModuleVersion = $this->xlsformModuleVersions
                    ->firstWhere('xlsform_module_id', $xlsformModule->id);



                if (! $currentModuleVersion) {
                    return;
                }

                if (! $currentModuleVersion->is_default) {
                    return;
                }

                $localModuleVersion = XlsformModuleVersion::query()
                    ->where('owner_id', $this->owner->id)
                    ->where('name', 'Local ' . $xlsformModule->name)
                    ->first();

                if (! $localModuleVersion) {
                    return;
                }

                // keep the local version in the same position the global one held, then drop the global
                $globalOrder = $currentModuleVersion->pivot->order;

                $this->xlsformModuleVersions()->sync([$localModuleVersion->id => ['order' => $globalOrder]], detaching: false);
                $this->xlsformModuleVersions()->detach($currentModuleVersion->id);
            });

    }


    /**
     * @throws BindingResolutionException
     * @throws \Exception
     */
    public function getSubmissions(): int
    {
        return app()->make(OdkLinkService::class)->getSubmissions($this);
    }

    public function getOneSubmission(): int
    {
        return app()->make(OdkLinkService::class)->getOneSubmission($this);
    }

    /**
     * @throws BindingResolutionException
     * @throws \Exception
     */
    public function getDraftSubmissions(): int
    {
        return app()->make(OdkLinkService::class)->getSubmissions($this, draft: true);
    }

    /** @return Attribute<?int, never> */
    protected function liveSubmissionsCount(): Attribute
    {
        return new Attribute(
            get: function (): ?int {
                return app()->make(OdkLinkService::class)->getSubmissionCount($this);
            },
        );
    }

    /** @return BelongsToMany<XlsformModuleVersion, $this> */
    public function xlsformModuleVersions(): BelongsToMany
    {
        return $this->belongsToMany(XlsformModuleVersion::class, 'selected_xlsform_module_versions')
            ->withPivot('order')
            ->orderByPivot('order', 'asc');
    }

    public function surveyRows(): HasManyDeep
    {
        return $this->hasManyDeep(
            SurveyRow::class,
            ['selected_xlsform_module_versions', XlsformModuleVersion::class],
        );
    }

    public function choiceLists(): HasManyDeep
    {
        return $this->hasManyDeep(
            ChoiceList::class,
            ['selected_xlsform_module_versions', XlsformModuleVersion::class],
        );
    }

    public function choiceListEntries(): HasManyDeep
    {
        return $this->hasManyDeep(
            ChoiceListEntry::class,
            ['selected_xlsform_module_versions', XlsformModuleVersion::class, ChoiceList::class],
        );
    }

    /**
     * @throws FileIsTooBig
     * @throws FileDoesNotExist
     */
    public function generateXlsfile(): PendingDispatch
    {
        // Ensure entity lists are populated before generating the XLS.
        // This is a no-op for templates without an entities sheet, and a self-healing
        // sync for templates created before entity list syncing was introduced.
        $this->xlsformTemplate->syncEntityListsFromFile();

        // mark form as unready
        $this->updateQuietly(['processing' => true]);

        $filePath = 'temp/' . $this->getKey() . '/' . $this->title . '.xlsx';
        $user = auth()->user();

        return Excel::queue(new XlsformWorkbookExport($this), $filePath, config('filament-odk-link.storage.xlsforms'))->chain(
            [
                new UpdateXlsformFile($this, $filePath),
            ]
        );

    }

    /**
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    public function deployDraft(bool $withMedia = true, bool $published = false): ?PendingDispatch
    {
        // if the form is already mid-processing, do not requeue.
        if ($this->processing) {
            return null;
        }

        // if this is immediately after publishing, skip regenerating the xlsfile
        if ($published) {
            return DeployDraftXlsformToOdkCentral::dispatch($this, $withMedia, auth()->user());
        }

        return $this->generateXlsfile()
            ->chain([
                new DeployDraftXlsformToOdkCentral($this, $withMedia, auth()->user()),
            ]);
    }

    /**
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     * @throws RequestException
     * @throws BindingResolutionException
     */
    public function publishForm(): ?PendingDispatch
    {
        if ($this->processing) {
            return null;
        }

        if ($this->draft_needs_update) {
            return $this->deployDraft()
                ->chain([
                    new PublishXlsformOnOdkCentral($this, auth()->user()),
                ]);
        }

        // if no draft update is needed, just publish the form:
        return PublishXlsformOnOdkCentral::dispatch($this, auth()->user());

    }

    /** Function to run after creation */
    public function setup(): void
    {
        $this->syncWithTemplate();
        $this->refresh();
        $this->deployDraft();
    }

    //
}
