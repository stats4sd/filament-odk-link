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
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Stats4sd\FilamentOdkLink\Jobs\UpdateXlsformTitleInFile;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsFormDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsFormDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\PublishesToOdkCentral;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class Xlsform extends Model implements HasMedia, WithXlsFormDrafts
{
    use HasXlsFormDrafts;
    use InteractsWithMedia;
    use PublishesToOdkCentral;

    protected $table = 'xlsforms';

    protected $guarded = [];

    protected static function booted()
    {

        // when the model is created;
        static::saved(static function (Xlsform $xlsform) {

            // copy the xlsfile from the template and update the title and id:
            if (! $xlsform->xlsfile) {
                $xlsform->updateXlsfileFromTemplate();
            }

            // if the odk_project is not set, set it based on the given owner:
            $xlsform->odk_project_id = $xlsform->owner->odkProject->id;
            $xlsform->saveQuietly();
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
            ->useDisk(config('odk-link.storage.xlsforms'));

        $this->addMediaCollection('attached_media')
            ->useDisk(config('odk-link.storage.xlsforms'));
    }

    // ****************** COMPUTED ATTRIBUTES ************************

    // Get an xlsformId string that is both human-readable and guaranteed to be unique within the platform
    public function xlsformId(): Attribute
    {
        return new Attribute(
            get: fn (): string => str($this->title)->slug() . '_' . $this->id,
        );
    }

    public function ownedByName(): Attribute
    {
        return new Attribute(
            get: fn (): string => $this->owner->{$this->getOwnerIdentifierAttributeName()} ?? '',
        );
    }

    public function currentVersion(): Attribute
    {
        return new Attribute(
            get: fn (): string => $this->xlsformVersions()->latest()->first()?->version ?? '',
        );
    }

    public function status(): Attribute
    {
        return new Attribute(
            get: fn () => $this->is_active ? 'LIVE' : ($this->odk_draft_token ? 'DRAFT' : 'NOT DEPLOYED'),
        );
    }

    // ****************** RELATIONSHIPS ************************

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function xlsformTemplate(): BelongsTo
    {
        return $this->belongsTo(XlsformTemplate::class);
    }

    public function xlsformVersions(): HasMany
    {
        return $this->hasMany(XlsformVersion::class);
    }

    public function submissions(): HasManyThrough
    {
        return $this->hasManyThrough(Submission::class, XlsformVersion::class);
    }

    // ***** RELATIONSHIPS VIA XLSFORM TEMPLATE *****

    public function requiredMedia(): HasMany
    {
        return $this->xlsformTemplate->requiredMedia();
    }

    public function attachedFixedMedia(): HasMany
    {
        return $this->xlsformTemplate->attachedFixedMedia();
    }

    public function attachedDataMedia(): HasMany
    {
        return $this->xlsformTemplate->attachedDataMedia();
    }

    // *********************** FUNCTIONS ****************************

    /**
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    public function updateXlsfileFromTemplate(): void
    {
        // copy media item from template:
        $this->xlsformTemplate->getFirstMedia('xlsform_file')?->copy($this, 'xlsform_file');

        $this->saveQuietly();
        UpdateXlsformTitleInFile::dispatchSync($this);
    }

    public function getOdkLinkAttribute(): ?string
    {
        $appends = ! $this->is_active ? '/draft' : '';

        return config('odk-link.odk.url') . '/#/projects/' . $this->owner->odkProject->id . '/forms/' . $this->odk_id . $appends;
    }
}
