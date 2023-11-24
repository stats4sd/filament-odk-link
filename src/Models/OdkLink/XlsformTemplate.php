<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsFormDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsFormDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\PublishesToOdkCentral;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class XlsformTemplate extends Model implements HasMedia, WithXlsFormDrafts
{
    use HasXlsFormDrafts;
    use InteractsWithMedia;
    use PublishesToOdkCentral;

    protected $table = 'xlsform_templates';

    protected $guarded = [];

    protected $casts = [
        'schema' => 'collection',
    ];

    protected static function booted(): void
    {
        static::deleting(static function (XlsformTemplate $xlsform) {
            $odkLinkService = app()->make(OdkLinkService::class);
            $xlsform->deleteFromOdkCentral($odkLinkService);
        });
    }

    // setup media library collections:
    // - xlsformfile
    // - attached media

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('xlsform_file')
            ->singleFile()
            ->useDisk(config('filament-odk-link.storage.xlsforms'));

        $this->addMediaCollection('attached_media')
            ->useDisk(config('filament-odk-link.storage.xlsforms'));
    }

    // ****************** COMPUTED ATTRIBUTES ************************

    // ****************** RELATIONSHIPS ************************

    public function submissions(): HasManyThrough
    {
        return $this->hasManyThrough(Submission::class, Xlsform::class);
    }

    public function xlsforms(): HasMany
    {
        return $this->hasMany(Xlsform::class);
    }

    public function activeXlsforms(): HasMany
    {
        return $this->hasMany(Xlsform::class)
            ->where('is_active', true);
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** 1 entry created for each required item as given from ODK Central */
    public function requiredMedia(): HasMany
    {
        return $this->hasMany(RequiredMedia::class);
    }

    /** filtered Required Media to only show media with type "image", "video" or "audio" */
    public function requiredFixedMedia(): HasMany
    {
        return $this->hasMany(RequiredMedia::class)
            ->where('required_media.type', '!=', 'file');
    }

    public function requiredDataMedia(): HasMany
    {
        return $this->hasMany(RequiredMedia::class)
            ->where('required_media.type', '=', 'file');
    }

    public function attachedFixedMedia(): HasMany
    {
        return $this->hasMany(RequiredMedia::class)
            ->where('required_media.type', '!=', 'file')
            ->whereHas('media');
    }

    public function attachedDataMedia(): HasMany
    {
        return $this->hasMany(RequiredMedia::class)
            ->where('required_media.type', '=', 'file')
            ->where(function (Builder $query) {
                $query->whereHas('media')
                    ->orWhere('required_media.dataset_id', '!=', null);
            });

    }

    public function datasets(): BelongsToMany
    {
        return $this->belongsToMany(Dataset::class)
            ->withPivot([
                'is_root',
                'is_repeat',
                'structure_item',
            ]);
    }

    public function xlsformTemplateSections(): HasMany
    {
        return $this->hasMany(XlsformTemplateSection::class);
    }

    public function repeatingSections(): HasMany
    {
        return $this->hasMany(XlsformTemplateSection::class)
            ->where('is_repeat', true);
    }

    public function rootSection(): HasOne
    {
        return $this->hasOne(XlsformTemplateSection::class)
            ->where('structure_item', 'root');
    }

    // ****************** METHODS ************************

    // get required media from ODK Central and store in the database
    public function getRequiredMedia(OdkLinkService $odkLinkService): void
    {
        $mediaItems = $odkLinkService->getRequiredMedia($this);

        foreach ($mediaItems as $mediaItem) {
            $this->requiredMedia()->updateOrCreate([
                'name' => $mediaItem['name'],
            ], [
                'type' => $mediaItem['type'],
                'exists_on_odk' => $mediaItem['exists'],
            ]);

        }

    }

    // get link to form in ODK Central
    public function getOdkLinkAttribute(): ?string
    {
        return config('filament-odk-link.odk.url') . '/#/projects/' . $this->owner->odkProject->id . '/forms/' . $this->odk_id . '/draft';
    }

    public function extractSections()
    {

        //***  extract structure into 'sections'


        // create or find the repeat sections
        $this->schema->filter(fn ($item) => $item['type'] === 'repeat')
            ->each(function ($item) {
                $this->repeatingSections()->updateOrCreate([
                    'structure_item' => $item['name'],
                ], [
                    'is_repeat' => true,
                    'schema' => $this->schema->filter(
                        fn ($subItem) => Str::contains($subItem['path'], $item['path'] . '/')
                        && $subItem['path'] !== $item['path']
                        && $subItem['type'] !== 'repeat'
                    ),
                ]);
            });

        // the above approach is fine unless there are nested repeats. Then, the inner repeat items will *also* be in the outer repeat schema.
        // To counter this, after each repeat group is created, we filter out any items that are in an innter repeat:

        $this->repeatingSections->each(function (XlsformTemplateSection $section) {
            $this->repeatingSections->each(function (XlsformTemplateSection $reviewSection) use ($section) {

                // don't compare the section to itself
                if ($reviewSection->structure_item === $section->structure_item) {
                    return;
                }

                // remove all items from the review section that have the same initial path as the $section.

                //                dump('Section x Seciton REveiw');
                //                dump('Section: ' . $section);
                //                dump('Rewveiw Section: ' . $reviewSection);
                //
                //
                //                dump($reviewSection->schema);
                $reviewSection->schema = $reviewSection->schema->filter(
                    fn ($item) => ! Str::startsWith($item['path'], '/' . $reviewSection->structure_item . '/' . $section->structure_item . '/')
                );

                $reviewSection->save();

            });
        });

        

        // TODO
        $this->repeatingSections->filter(fn ($item) => $item->structure_item !== 'root')
            ->map(function (int $item, int $key) {

                dd($item);
        });


        // create or find the 'root' section
        $this->xlsformTemplateSections()->updateOrCreate([
            'structure_item' => 'root',
        ], [
            'is_repeat' => false,
            // 'schema' => $this->schema->filter(fn ($item) => $item['type'] !== 'structure' && $item['type'] !== 'repeat' && $item['path'] === "/{$item['name']}"),
            'schema' => $this->schema->filter(fn ($item) => $item['type'] !== 'structure' && $item['type'] !== 'repeat'),
        ]);


        //        dd('ok');

        return $this->xlsformTemplateSections;
    }
}
