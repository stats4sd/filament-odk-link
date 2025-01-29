<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Stats4sd\FilamentOdkLink\Jobs\UpdateXlsformTitleInFile;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Abstracts\HasXlsformDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\PublishesToOdkCentral;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class Xlsform extends HasXlsformDrafts implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'xlsforms';

    protected $casts = [
        'schema' => 'collection',
    ];

    protected static function booted(): void
    {

        // when the model is created;
        static::saved(static function (Xlsform $xlsform) {
            $xlsform->syncWithTemplate();
        });

        static::deleting(static function (Xlsform $xlsform) {
            $odkLinkService = app()->make(OdkLinkService::class);
            $xlsform->deleteFromOdkCentral($odkLinkService);
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
            get: fn (): string => str($this->title)->slug() . '_' . $this->id,
        );
    }

    /** @return Attribute<string, never> */
    protected function currentVersion(): Attribute
    {
        return new Attribute(
            get: fn (): string => $this->xlsformVersions()->latest()->first()->version ?? '',
        );
    }

    /** @return Attribute<string, never> */
    protected function status(): Attribute
    {
        return new Attribute(
            get: function (): string {
                if (! $this->has_latest_template || ! $this->has_latest_media) {
                    return 'UPDATES AVAILABLE';
                }
                if ($this->is_active) {
                    return 'LIVE';
                }

                if ($this->odk_draft_tokwn) {
                    return 'DRAFT';
                }

                return 'NOT DEPLOYED';
            },
        );
    }

    // ****************** RELATIONSHIPS ************************

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

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

    // *********************** FUNCTIONS ****************************

    public function getOdkLinkAttribute(): ?string
    {
        $appends = ! $this->is_active ? '/draft' : '';

        return config('filament-odk-link.odk.url') . '/#/projects/' . $this->owner->odkProject->id . '/forms/' . $this->odk_id . $appends;
    }

    // make sure the xlsform is using the latest template
    public function syncWithTemplate(): void
    {
        // copy the xlsfile from the template;

        // TEMP fix for namespace clashes
        // TODO - find a permanent fix for this
        if (! $xlsfile = $this->xlsformTemplate->getFirstMedia('xlsform_file')) {

            // check if \App\Models\XlsformTemplate exists
            if (class_exists('\App\Models\XlsformTemplate')) {

                $xlsfile = \App\Models\XlsformTemplate::find($this->xlsformTemplate->id)
                    ->getFirstMedia('xlsform_file');

            }
        }

        $xlsfile->copy($this, 'xlsform_file');
        $this->saveQuietly();

        // update form title and ID in the file itself (ODK Central looks for these values in the XLS file)
        UpdateXlsformTitleInFile::dispatchSync($this);

        // if the odk_project is not set, set it based on the given owner:
        $this->odk_project_id = $this->owner->odkProject->id;
        $this->has_latest_template = true;
        $this->saveQuietly();
    }

    public function getSubmissions(): int
    {
        return app()->make(OdkLinkService::class)->getSubmissions($this);
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
}
