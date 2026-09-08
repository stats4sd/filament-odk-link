<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Stats4sd\FilamentOdkLink\Models\Country;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\XlsformModuleVersionLocale;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class XlsformModuleVersion extends Model implements HasMedia
{
    use HasRelationships;

    protected $table = 'xlsform_module_versions';

    protected $guarded = [];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    use InteractsWithMedia;

    // TODO: impliment WithXlsformDrafts to enable pyxform validation checks + module testing/drafts.

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('xlsform_file')
            ->singleFile()
            ->useDisk(config('filament-odk-link.storage.xlsforms'));
    }

    /** @return BelongsTo<XlsformModule, $this> */
    public function xlsformModule(): BelongsTo
    {
        return $this->belongsTo(XlsformModule::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    // ** **** Xlsform Components ********

    /** @return Attribute<string, never> */
    public function xlsfile(): Attribute
    {
        return new Attribute(
            get: fn (): string => $this->getFirstMediaPath('xlsform_file'),
        );
    }

    /** @return HasMany<SurveyRow, $this> */
    public function surveyRows(): HasMany
    {
        return $this->hasMany(SurveyRow::class)->orderBy('row_number');
    }

    /** @return HasMany<ChoiceList, $this> */
    public function choiceLists(): HasMany
    {
        return $this->hasMany(ChoiceList::class);
    }

    /** @return HasManyThrough<ChoiceListEntry, ChoiceList, $this> */
    public function choiceListEntries(): HasManyThrough
    {
        return $this->hasManyThrough(ChoiceListEntry::class, ChoiceList::class, 'xlsform_module_version_id', 'choice_list_id', 'id', 'id');
    }

    /** @return BelongsToMany<Locale, $this, XlsformModuleVersionLocale> */
    public function locales(): BelongsToMany
    {
        return $this->belongsToMany(Locale::class, 'xlsform_module_version_locale', 'xlsform_module_version_id', 'locale_id')
            ->using(XlsformModuleVersionLocale::class)
            ->withPivot(['needs_update']);
    }

    /** @return HasMany<XlsformModuleVersionLocale, $this> */
    public function xlsformModuleVersionLocales(): HasMany
    {
        return $this->hasMany(XlsformModuleVersionLocale::class);
    }

    // Split up language strings into 2 relationships

    /** @return HasManyThrough<LanguageString, SurveyRow, $this> */
    public function surveyLanguageStrings(): HasManyThrough
    {
        return $this->hasManyThrough(LanguageString::class, SurveyRow::class, 'xlsform_module_version_id', 'linked_entry_id', 'id', 'id')
            ->where('language_strings.linked_entry_type', SurveyRow::class);
    }

    /** @return HasManyDeep<LanguageString, $this> */
    public function choiceListEntryLanguageStrings(): HasManyDeep
    {
        return $this->hasManyDeep(
            LanguageString::class,
            [ChoiceList::class, ChoiceListEntry::class],
            ['xlsform_module_version_id', 'choice_list_id', ['linked_entry_type', 'linked_entry_id']],
        );
    }

    /** @return BelongsToMany<Xlsform, $this> */
    public function xlsforms(): BelongsToMany
    {
        return $this->belongsToMany(Xlsform::class, 'selected_xlsform_module_versions')
            ->withPivot(['order']);
    }

    /** @return BelongsTo<Model, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('filament-odk-link.models.form_owner'), 'owner_id');
    }

    /**
     * Deep-clone this version for a specific owner.
     *
     * Copies the version record, all ChoiceLists (and their visible
     * ChoiceListEntries + LanguageStrings), all SurveyRows (+ their
     * LanguageStrings), and the locale pivot records. The IsLookupList
     * global scope on ChoiceListEntry is intentionally preserved so only
     * entries visible to the current owner are cloned.
     */
    public function cloneForOwner(WithXlsforms $owner): static
    {
        return DB::transaction(function () use ($owner) {
            $newVersion = $this->replicate();
            $newVersion->owner_id = $owner->getKey();
            $newVersion->is_default = false;
            $newVersion->name = ($this->xlsformModule->name ?? $this->name) . ' - ' . $owner->getName();
            $newVersion->save();

            // Clone ChoiceLists first; build an old→new ID map for SurveyRow references.
            $choiceListMap = [];
            foreach ($this->choiceLists()->get() as $choiceList) {
                $newList = $choiceList->replicate(['xlsform_module_version_id']);
                $newList->xlsform_module_version_id = $newVersion->id;
                $newList->save();
                $choiceListMap[$choiceList->id] = $newList->id;

                foreach ($choiceList->choiceListEntries()->get() as $entry) {
                    $newEntry = $entry->replicate(['choice_list_id']);
                    $newEntry->choice_list_id = $newList->id;
                    $newEntry->save();

                    foreach ($entry->languageStrings()->get() as $ls) {
                        $newLs = $ls->replicate(['linked_entry_id']);
                        $newLs->linked_entry_id = $newEntry->id;
                        $newLs->save();
                    }
                }
            }

            foreach ($this->surveyRows()->get() as $row) {
                $newRow = $row->replicate(['xlsform_module_version_id', 'choice_list_id']);
                $newRow->xlsform_module_version_id = $newVersion->id;
                $newRow->choice_list_id = $row->choice_list_id
                    ? ($choiceListMap[$row->choice_list_id] ?? null)
                    : null;
                $newRow->save();

                foreach ($row->languageStrings()->get() as $ls) {
                    $newLs = $ls->replicate(['linked_entry_id']);
                    $newLs->linked_entry_id = $newRow->id;
                    $newLs->save();
                }
            }

            foreach ($this->locales()->get() as $locale) {
                $newVersion->locales()->attach($locale->id, [
                    'needs_update' => false,
                    'updated_during_import' => false,
                ]);
            }

            return $newVersion;
        });
    }
}
