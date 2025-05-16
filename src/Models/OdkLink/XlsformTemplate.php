<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Abstracts\HasXlsformDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class XlsformTemplate extends HasXlsformDrafts implements HasMedia
{
    use HasRelationships;

    protected $table = 'xlsform_templates';

    protected $casts = [
        'schema' => 'collection',
    ];

    protected static function booted(): void
    {

        static::deleting(static function (XlsformTemplate $xlsformTemplate) {
            $odkLinkService = app()->make(OdkLinkService::class);
            $xlsformTemplate->deleteFromOdkCentral($odkLinkService);
        });

        // if the media files are updated, we need to update the test form in ODK Central
        static::updated(static function (XlsformTemplate $xlsformTemplate) {
            $odkLinkService = app()->make(OdkLinkService::class);

            $xlsformTemplate->deployDraft($odkLinkService);

            // mark all other xlsforms using this template as not current
            $xlsformTemplate->markAllAsNotCurrent();

            // If the template is available, add a version of it to all teams where `shouldReceiveAllXlsformTemplates` is true
            if ($xlsformTemplate->available) {

                $xlsformOwners = collect(get_declared_classes())
                    // get all classes that implement WithXlsforms
                    ->filter(fn ($class) => collect(class_implements($class))->contains(WithXlsforms::class))
                    ->map(fn (string|Model $class) => $class::all()
                        ->filter(fn (WithXlsforms $owner) => $owner->should_receive_all_templates)
                        ->each(function (WithXlsforms $owner) use ($xlsformTemplate) {
                            $xlsform = $owner->xlsforms()->whereHas('xlsformTemplate', function ($query) use ($xlsformTemplate) {
                                $query->where('xlsform_templates.id', $xlsformTemplate->id);
                            })->first();

                            if (! $xlsform) {
                                $xlsform = $xlsformTemplate->xlsforms()->create([
                                    'owner_id' => $owner->getKey(),
                                    'owner_type' => get_class($owner),
                                    'title' => $xlsformTemplate->title,
                                ]);
                            }
                        })
                    );
            }
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

    public function deployDraft(OdkLinkService $service, bool $withMedia = true): bool
    {
        return $this->sendDraftToOdkCentral($service, $withMedia);
    }

    // ****************** RELATIONSHIPS ************************

    /** @return HasManyThrough<Submission, Xlsform, $this> */
    public function submissions(): HasManyThrough
    {
        return $this->hasManyThrough(Submission::class, Xlsform::class);
    }

    /** @return HasMany<Xlsform, $this> */
    public function xlsforms(): HasMany
    {
        return $this->hasMany(Xlsform::class);
    }

    /** @return HasMany<Xlsform, $this> */
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
    /** @return HasMany<RequiredMedia, $this> */
    public function requiredMedia(): HasMany
    {
        return $this->hasMany(RequiredMedia::class);
    }

    /** filtered Required Media to only show media with type "image", "video" or "audio" */
    /** @return HasMany<RequiredMedia, $this> */
    public function requiredFixedMedia(): HasMany
    {
        return $this->hasMany(RequiredMedia::class)
            ->where('required_media.type', '!=', 'file');
    }

    /** @return HasMany<RequiredMedia, $this> */
    public function requiredDataMedia(): HasMany
    {
        return $this->hasMany(RequiredMedia::class)
            ->where('required_media.type', '=', 'file');
    }

    /** @return HasMany<RequiredMedia, $this> */
    public function attachedFixedMedia(): HasMany
    {
        return $this->hasMany(RequiredMedia::class)
            ->where('required_media.type', '!=', 'file')
            ->whereHas('media');
    }

    /** @return HasMany<RequiredMedia, $this> */
    public function attachedDataMedia(): HasMany
    {
        return $this->hasMany(RequiredMedia::class)
            ->where('required_media.type', '=', 'file')
            ->where(function (Builder $query) {
                $query->whereHas('media')
                    // TODO: how to bring together Dataset and ChoiceList?
                    ->orWhere('required_media.dataset_id', '!=', null);
            });

    }

    /** @return BelongsToMany<Dataset, $this> */
    public function datasets(): BelongsToMany
    {
        return $this->belongsToMany(Dataset::class, 'required_media')
            ->withPivot([
                'name',
                'type',
                'is_static',
                'exists_on_odk',
            ])
            ->using(RequiredMedia::class);
    }

    /** @return HasMany<XlsformTemplateSection, $this> */
    public function xlsformTemplateSections(): HasMany
    {
        return $this->hasMany(XlsformTemplateSection::class);
    }

    /** @return HasMany<XlsformTemplateSection, $this> */
    public function repeatingSections(): HasMany
    {
        return $this->hasMany(XlsformTemplateSection::class)
            ->where('is_repeat', true);
    }

    /** @return HasOne<XlsformTemplateSection, $this> */
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
                'updated_during_import' => true,
            ]);

        }

        // remove any media that are no longer needed
        $this->requiredMedia()->where('updated_during_import', false)->delete();

    }

    // get link to form in ODK Central
    public function getOdkLinkAttribute(): ?string
    {
        return config('filament-odk-link.odk.url').'/#/projects/'.$this->owner->odkProject->id.'/forms/'.$this->odk_id.'/draft';
    }

    /** @return Collection<XlsformTemplateSection> */
    public function extractSections(): Collection
    {

        // set all existing sections to not current.
        $this->repeatingSections()->each(fn ($section) => $section->is_current = false);

        // create or find the repeat sections
        $this->schema->filter(fn ($item) => $item['type'] === 'repeat')
            ->each(function ($item) {
                $this->repeatingSections()->updateOrCreate([
                    'structure_item' => $item['name'],
                ], [
                    'is_repeat' => true,
                    'is_current' => true,
                    'schema' => $this->schema->filter(
                        fn ($subItem) => Str::contains($subItem['path'], $item['path'].'/')
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
                    fn ($item) => ! Str::startsWith($item['path'], '/'.$reviewSection->structure_item.'/'.$section->structure_item.'/')
                );

                $reviewSection->save();

            });
        });

        // find all ODK variable names of all repeating sections
        $repeatingSectionItemNames = [];

        foreach ($this->repeatingSections as $repeatingSection) {
            $variableNames = $repeatingSection->schema->pluck('name');

            foreach ($variableNames as $variableName) {
                array_push($repeatingSectionItemNames, $variableName);
            }
        }

        // create or find the 'root' section
        $rootSection = $this->xlsformTemplateSections()->updateOrCreate([
            'structure_item' => 'root',
        ], [
            'is_repeat' => false,
            'is_current' => true,
            // to exclude below items:
            // 1. structure type item
            // 2. repeat type item
            // 3. item names belong to ODK variable names of all repeating sections
            'schema' => $this->schema->filter(fn ($item) => $item['type'] !== 'structure' && $item['type'] !== 'repeat' && ! in_array($item['name'], $repeatingSectionItemNames)),
        ]);

        // add the root as the parent of the repeating sections
        // TODO: update to handle nested repeats

        $this->repeatingSections()->update([
            'parent_id' => $rootSection->id,
        ]);

        return $this->xlsformTemplateSections;
    }

    // mark all xlsforms using this template as not current (i.e. not using the latest template)
    public function markAllAsNotCurrent(): void
    {
        $this->xlsforms()->update(['has_latest_template' => false]);
    }

    /** @return HasMany<XlsformModule, $this> */
    public function xlsformModules(): HasMany
    {
        return $this->hasMany(XlsformModule::class);
    }

    /** @return HasManyThrough<XlsformModuleVersion, XlsformModule, $this> */
    public function xlsformModuleVersions(): HasManyThrough
    {
        return $this->hasManyThrough(XlsformModuleVersion::class, XlsformModule::class, 'form_id', 'xlsform_module_id')
            ->where('xlsform_modules.form_type', static::class);
    }

    /** @return HasManyDeep<SurveyRow, $this> */
    public function surveyRows(): HasManyDeep
    {
        return $this->hasManyDeep(
            SurveyRow::class,
            [XlsformModule::class, XlsformModuleVersion::class],
            [['form_type', 'form_id'], null, 'xlsform_module_version_id']
        );
    }

    /** @return HasManyDeep<ChoiceList, $this> */
    public function choiceLists(): HasManyDeep
    {
        return $this->hasManyDeep(
            ChoiceList::class,
            [XlsformModule::class, XlsformModuleVersion::class],
            [['form_type', 'form_id'], null, 'xlsform_module_version_id']
        );
    }

    /** @return HasManyDeep<ChoiceListEntry, $this> */
    public function choiceListEntries(): HasManyDeep
    {
        return $this->hasManyDeep(
            ChoiceListEntry::class,
            [XlsformModule::class, XlsformModuleVersion::class, ChoiceList::class],
            [['form_type', 'form_id'], null, 'xlsform_module_version_id', 'choice_list_id']
        );
    }

    // Split up language strings into 2 relationships as there are 2 paths between xlsformtemplates and language strings.

    /** @return HasManyDeep<LanguageString, $this> */
    public function surveyLanguageStrings(): HasManyDeep
    {
        return $this->hasManyDeep(
            LanguageString::class,
            [XlsformModule::class, XlsformModuleVersion::class, SurveyRow::class],
            [['form_type', 'form_id'], 'xlsform_module_id', 'xlsform_module_version_id', ['linked_entry_type', 'linked_entry_id']],
        );
    }

    /** @return HasManyDeep<LanguageString, $this> */
    public function choiceListEntryLanguageStrings(): HasManyDeep
    {
        return $this->hasManyDeep(
            LanguageString::class,
            [XlsformModule::class, XlsformModuleVersion::class, ChoiceList::class, ChoiceListEntry::class],
            [['form_type', 'form_id'], 'xlsform_module_id', 'xlsform_module_version_id', 'choice_list_id', ['linked_entry_type', 'linked_entry_id']],
        );
    }

    // for a template to be available in a locale, *every* module should be linked to that locale

    /** @return Attribute<Collection, never> */
    protected function locales(): Attribute
    {
        return new Attribute(
            get: function (): Collection {

                // get set of locales for each default module version
                $locales = $this->xlsformModules->map(
                    fn (XlsformModule $xlsformModule) => $xlsformModule
                        ->defaultXlsformVersion
                        ->locales
                );

                // get list of locales present for *every* module
                return $locales->reduce(function ($carry, $item) {
                    return $carry->intersect($item);
                }, $locales->first())
                    ->values();
            }
        );
    }
}
