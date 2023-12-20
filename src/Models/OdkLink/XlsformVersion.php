<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class XlsformVersion extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'xlsform_versions';

    protected $guarded = [];

    protected $casts = [
        'schema' => 'collection',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('xlsform_file')
            ->singleFile()
            ->useDisk(config('filament-odk-link.storage.xlsforms'));

        $this->addMediaCollection('attached_media')
            ->useDisk(config('filament-odk-link.storage.xlsforms'));
    }

    // **************** COMPUTED ATTRIBUTES ***********************

    // If no title is given, add a default title by combining the owner name and template title.
    public function title(): Attribute
    {
        return new Attribute(
            get: fn(): string => $this->team ? $this->team->name . ' - ' . $this->xlsform->title : '',
        );
    }

    public function xlsfile(): Attribute
    {
        return new Attribute(
            get: fn(): string => $this->getFirstMediaPath('xlsform_file'),
        );
    }

    public function xlsfile_name(): Attribute
    {
        return new Attribute(
            get: fn(): string => $this->getFirstMedia('xlsform_file')->file_name,
        );
    }

    // ************ RELATIONSHIPS ***************

    public function xlsform(): BelongsTo
    {
        return $this->belongsTo(Xlsform::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
