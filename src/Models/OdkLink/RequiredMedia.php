<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class RequiredMedia extends Pivot implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'required_media';

    protected $casts = [
        'is_static' => 'boolean',
    ];

    protected static function booted(): void
    {

        // when deleting, also delete any attached media;
        static::deleting(static function ($requiredMedia) {
            $requiredMedia->getMedia()
                ->each(fn ($media) => $requiredMedia->deleteMedia($media));
        });

        // when updating, update the related xlsform template to set draft_needs_updating to true (to ensure the updated media is pushed to ODK Central for testing
        static::saved(static function (RequiredMedia $requiredMedia) {
            $requiredMedia->xlsformTemplate->updateQuietly(
                ['draft_needs_updating' => true]
            );

        });
    }

    public function status(): Attribute
    {
        return new Attribute(
            get: fn (): string => $this->dataset_id || $this->hasMedia() ? 1 : 0,
        );
    }

    public function fullType(): Attribute
    {
        return new Attribute(
            get: fn (): string => $this->is_static ? $this->type : 'dataset',
        );
    }

    public function xlsformTemplate(): BelongsTo
    {
        return $this->belongsTo(XlsformTemplate::class);
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    // maybe need to get imageUrl (for media attachments) and/or dataset attachment...

}
