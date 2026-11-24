<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Contracts\Auth\Authenticatable;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
use Throwable;

class Xlsform extends HasXlsformDrafts implements HasMedia
{
    use HasRelationships;

    protected $table = 'xlsforms';

    protected $casts = [
        'schema' => 'collection',
        'odk_draft_updated_at' => 'timestamp',
        'odk_published_at' => 'timestamp',
        'has_locales' => 'boolean',
        'draft_snapshot_at' => 'datetime',
        'draft_claimed_at' => 'datetime',
    ];

    public const CLAIM_TTL_MINUTES = 15;

    public const DRAFT_STATE_UPDATING = 'updating';

    public const DRAFT_STATE_MISSING = 'missing';

    public const DRAFT_STATE_EXPIRED = 'expired';

    public const DRAFT_STATE_CURRENT = 'current';

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
        $alreadyHasModules = $this->xlsformModuleVersions->isNotEmpty();

        // check through the template modules; If this form is missing any, add the default version
        $this->xlsformTemplate->xlsformModules
            ->sortBy('default_order')

            // check for modules where the module version is not _already_ linked to this form (to avoid resetting custom ordering)
            ->filter(fn(XlsformModule $module) => $this->xlsformModuleVersions->doesntContain('xlsform_module_id', $module->id))
            ->each(function (XlsformModule $xlsformModule) use ($alreadyHasModules) {

                // A form that already has modules keeps their order (a team may have re-ordered them), so a module that
                // was added to the template later is slotted in after the module that precedes it in the template.
                $order = $alreadyHasModules && $xlsformModule->default_order !== null
                    ? $this->makeRoomForModule($xlsformModule)
                    : $xlsformModule->default_order;

                $this->xlsformModuleVersions()->attach($xlsformModule->defaultXlsformVersion, ['order' => $order]);

                // If the XlsformModule `can_be_extended` add a 'local' version of the module immediately after it
                if ($xlsformModule->can_be_extended) {
                    $localModuleVersion = XlsformModuleVersion::firstOrCreate([
                        'owner_id' => $this->owner_id,
                        'name' => 'Local ' . $xlsformModule->name,
                    ]);

                    $this->xlsformModuleVersions()->sync([$localModuleVersion->id => ['order' => $order + 1]], detaching: false);
                }
            });

        // reload the pivot so the swap below sees any versions just attached
        $this->load('xlsformModuleVersions');
        $this->localiseModules();

        $this->has_latest_template = true;
        $this->saveQuietly();
    }

    // Opens up the order positions a new module needs (one, or two when it also gets a 'Local' version) directly after
    // the module that comes before it in the template, and returns the first of them. Everything after that is pushed
    // back, so the relative order of the modules already in the form does not change.
    private function makeRoomForModule(XlsformModule $newModule): int
    {
        $selectedVersions = $this->xlsformModuleVersions()->get();

        $precedingModule = $this->xlsformTemplate->xlsformModules
            ->filter(fn(XlsformModule $module) => $module->default_order !== null && $module->default_order < $newModule->default_order)
            ->filter(fn(XlsformModule $module) => $selectedVersions->contains(fn(XlsformModuleVersion $version) => $this->isVersionOfModule($version, $module)))
            ->sortByDesc('default_order')
            ->first();

        $insertAt = $precedingModule
            ? $selectedVersions
                ->filter(fn(XlsformModuleVersion $version) => $this->isVersionOfModule($version, $precedingModule))
                ->max(fn(XlsformModuleVersion $version) => data_get($version, 'pivot.order')) + 1
            : $selectedVersions->min(fn(XlsformModuleVersion $version) => data_get($version, 'pivot.order')) ?? 1;

        DB::table('selected_xlsform_module_versions')
            ->where('xlsform_id', $this->getKey())
            ->where('order', '>=', $insertAt)
            ->increment('order', $newModule->can_be_extended ? 2 : 1);

        return $insertAt;
    }

    // a module's versions are its global, local-replacement and cloned versions, plus the 'Local {module}' extension that has no module id
    private function isVersionOfModule(XlsformModuleVersion $version, XlsformModule $module): bool
    {
        if ($version->xlsform_module_id === $module->id) {
            return true;
        }

        return $version->xlsform_module_id === null
            && $version->owner_id === $this->owner_id
            && $version->name === 'Local ' . $module->name;
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
                    ->where('owner_id', $this->owner_id)
                    ->where('xlsform_module_id', $xlsformModule->id)
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
    public function generateXlsfile(?Authenticatable $user = null): PendingDispatch
    {
        $user ??= auth()->user();

        // Ensure entity lists are populated before generating the XLS.
        // This is a no-op for templates without an entities sheet, and a self-healing
        // sync for templates created before entity list syncing was introduced.
        $this->xlsformTemplate->syncEntityListsFromFile();

        // mark form as unready
        $this->updateQuietly(['processing' => true]);

        $filePath = 'temp/' . $this->getKey() . '/' . $this->title . '.xlsx';

        // Only deployDraft() calls this, and it chains the deploy job after the file job:
        // the claim must stay held until that deploy job has finished.
        return Excel::queue(new XlsformWorkbookExport($this, $user), $filePath, config('filament-odk-link.storage.xlsforms'))->chain(
            [
                new UpdateXlsformFile($this, $filePath, $user, holdClaim: true),
            ]
        );

    }

    /**
     * Atomically take the right to build and deploy this form. Only one caller can win; a claim that nobody
     * released (killed worker, lost job) can be taken again after CLAIM_TTL_MINUTES.
     */
    public function claimForDeployment(): bool
    {
        $claimedAt = now();
        $expiredBefore = now()->subMinutes(self::CLAIM_TTL_MINUTES);

        $claimedRows = static::withoutGlobalScopes()
            ->whereKey($this->getKey())
            ->where(function (Builder $query) use ($expiredBefore): void {
                $query->where('processing', false)
                    ->orWhereNull('draft_claimed_at')
                    ->orWhere('draft_claimed_at', '<', $expiredBefore);
            })
            ->update([
                'processing' => true,
                'draft_claimed_at' => $claimedAt,
            ]);

        if ($claimedRows === 0) {
            return false;
        }

        $this->forceFill([
            'processing' => true,
            'draft_claimed_at' => $claimedAt,
        ])->syncOriginalAttributes(['processing', 'draft_claimed_at']);

        return true;
    }

    public function releaseClaim(bool $needsUpdate = false): void
    {
        $values = [
            'processing' => false,
            'draft_claimed_at' => null,
        ];

        if ($needsUpdate) {
            $values['draft_needs_update'] = true;
        }

        $this->writeWithoutEvents($values);
    }

    public function hasActiveClaim(): bool
    {
        if (! $this->processing) {
            return false;
        }

        if (! $this->draft_claimed_at) {
            return false;
        }

        return $this->draft_claimed_at->gt(now()->subMinutes(self::CLAIM_TTL_MINUTES));
    }

    public function draftIsOutdated(): bool
    {
        if ($this->draft_needs_update) {
            return true;
        }

        if (! $this->has_draft) {
            return true;
        }

        return $this->processing && ! $this->hasActiveClaim();
    }

    public function draftState(): string
    {
        if ($this->hasActiveClaim()) {
            return self::DRAFT_STATE_UPDATING;
        }

        if (! $this->has_draft) {
            return self::DRAFT_STATE_MISSING;
        }

        if ($this->draftIsOutdated()) {
            return self::DRAFT_STATE_EXPIRED;
        }

        return self::DRAFT_STATE_CURRENT;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function writeWithoutEvents(array $values): void
    {
        static::withoutGlobalScopes()
            ->whereKey($this->getKey())
            ->update($values);

        $this->forceFill($values)->syncOriginalAttributes(array_keys($values));
    }

    /**
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    public function deployDraft(
        bool $withMedia = true,
        bool $published = false,
        bool $alreadyClaimed = false,
        bool $holdClaim = false,
        ?Authenticatable $user = null,
    ): ?PendingDispatch {
        // if the form is already mid-processing, do not requeue.
        if (! $alreadyClaimed) {
            if (! $this->claimForDeployment()) {
                return null;
            }
        }

        $user ??= auth()->user();

        // if this is immediately after publishing, skip regenerating the xlsfile.
        // The draft is the same file as before: leave the flag and the snapshot alone.
        if ($published) {
            return DeployDraftXlsformToOdkCentral::dispatch($this, $withMedia, $user, null, $holdClaim);
        }

        // Everything saved before this moment goes into the draft. The flag is cleared here, so a change saved
        // while the chain runs keeps it set and the form shows as outdated afterwards.
        $snapshotAt = now();

        $this->writeWithoutEvents(['draft_needs_update' => false]);

        try {
            return $this->generateXlsfile($user)
                ->chain([
                    new DeployDraftXlsformToOdkCentral($this, $withMedia, $user, $snapshotAt, $holdClaim),
                ]);
        } catch (Throwable $exception) {
            $this->releaseClaim(needsUpdate: true);

            throw $exception;
        }
    }

    /**
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     * @throws RequestException
     * @throws BindingResolutionException
     */
    public function publishForm(): ?PendingDispatch
    {
        if (! $this->claimForDeployment()) {
            return null;
        }

        $user = auth()->user();

        // the claim stays held through the draft deploy, the publish and the final draft re-deploy.
        if ($this->draft_needs_update) {
            $deployment = $this->deployDraft(alreadyClaimed: true, holdClaim: true, user: $user);

            if (! $deployment) {
                Log::warning("Publishing skipped for xlsform {$this->id}: the draft deployment could not be queued.");
                $this->releaseClaim();

                return null;
            }

            return $deployment->chain([
                new PublishXlsformOnOdkCentral($this, $user),
            ]);
        }

        // if no draft update is needed, just publish the form:
        return PublishXlsformOnOdkCentral::dispatch($this, $user);

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
