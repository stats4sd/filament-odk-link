<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\Client\RequestException;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Stats4sd\FilamentOdkLink\Exports\XlsformExport\XlsformWorkbookExport;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\DeployDraftXlsformToOdkCentral;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\NotifyUserThatXlsformFileIsDeployedAsDraft;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\NotifyUserThatXlsformFileIsUpdated;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\UpdateXlsformFile;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Abstracts\HasXlsformDrafts;
use Stats4sd\FilamentOdkLink\Services\HelperService;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class Xlsform extends HasXlsformDrafts implements HasMedia
{

    use HasRelationships;

    protected $table = 'xlsforms';

    protected $casts = [
        'schema' => 'collection',
        'odk_draft_updated_at' => 'timestamp',
        'odk_published_at' => 'timestamp',
    ];

    // ******************* SETUP ***********************************
    protected static function booted(): void
    {
        // when the model is created;
        static::saved(static function (self $xlsform) {
            $xlsform->syncWithTemplate();
        });

        static::created(static function (self $xlsform) {
            $xlsform->deployDraft();
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

                if (!$this->odk_draft_token) {
                    return 'NOT DEPLOYED';
                }

                if (!$this->has_latest_template || !$this->has_latest_media) {
                    return 'UPDATES AVAILABLE';
                }

                if ($this->is_active) {
                    return 'LIVE';
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

    /** @return HasManyThrough<Submission, XlsformVersion, $this> */
    public function submissions(): HasManyThrough
    {
        return $this->hasManyThrough(Submission::class, XlsformVersion::class);
    }

    // ***** RELATIONSHIPS VIA XLSFORM TEMPLATE *****

    /** @return HasMany<RequiredMedia, XlsformTemplate> */
    public function requiredMedia(): HasMany
    {
        return $this->xlsformTemplate->requiredMedia();
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
            ->filter(fn(XlsformModule $module) => $this->xlsformModuleVersions->doesntContain('module_id', $module->id))
            ->each(fn(XlsformModule $xlsformModule) => $this->xlsformModuleVersions()->attach($xlsformModule->defaultXlsformVersion));


        $this->has_latest_template = true;
        $this->saveQuietly();
    }

    /**
     * @throws BindingResolutionException
     * @throws \Exception
     */
    public function getSubmissions(): int
    {
        return app()->make(OdkLinkService::class)->getSubmissions($this);
    }

    /**
     * @throws BindingResolutionException
     * @throws \Exception
     */
    public function getDraftSubmissions(): int
    {
        return app()->make(OdkLinkService::class)->getSubmissions($this, draft: true);
    }

    public function getLiveSubmissionCount(): ?int
    {
        return app()->make(OdkLinkService::class)->getSubmissionCount($this);
    }

    // Get the live submissions count from ODK Central

    /** @return Attribute<?int, never> */
    protected function liveSubmissionsCount(): Attribute
    {
        return new Attribute(
            get: function (): ?int {
                return $this->getLiveSubmissionCount();
            },
        );
    }

    /** @return BelongsToMany<XlsformModuleVersion, $this> */
    public function xlsformModuleVersions(): BelongsToMany
    {
        return $this->belongsToMany(XlsformModuleVersion::class, 'selected_xlsform_module_versions')
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
        ray('generateXlsfile for . ' . $this->title);

        // mark form as unready
        $this->update(['processing' => true]);

        $filePath = 'temp/' . $this->getKey() . '/' . $this->title . '.xlsx';
        $user = auth()->user();

        return Excel::queue(new XlsformWorkbookExport($this), $filePath, config('filament-odk-link.storage.xlsforms'))->chain(
            [
                new UpdateXlsformFile($this, $filePath),
                new NotifyUserThatXlsformFileIsUpdated($this, $user),
            ]
        );

    }

    /**
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    public function deployDraft(bool $withMedia = true): PendingDispatch
    {
        ray('deployDraft for . ' . $this->title);

        return $this->generateXlsfile()
            ->chain([
                new DeployDraftXlsformToOdkCentral($this, $withMedia, auth()->user()),
                new NotifyUserThatXlsformFileIsDeployedAsDraft($this, auth()->user()),
            ]);
    }

    /**
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     * @throws RequestException
     * @throws BindingResolutionException
     */
    public function publishForm(): XlsformVersion
    {
        ray('publishForm for . ' . $this->title);

        $odkLinkService = app()->make(OdkLinkService::class);
        $newVersion = $odkLinkService->publishForm($this);

        // immediately after publishing, create a new draft. We always want a draft version available to the platform and users.
        $this->deployDraft();

        return $newVersion;
    }
}
