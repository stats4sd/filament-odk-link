<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use App\Models\Reference\Country;
use App\Models\Team;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\XlsformModuleVersionLocale;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class XlsformModuleVersion extends Model implements HasMedia
{
    use HasRelationships;

    protected $table = 'xlsform_module_versions';

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

    public function xlsformModule(): BelongsTo
    {
        return $this->belongsTo(XlsformModule::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    // Which teams have selected to use this module instead of the default?
    // Linked Via chosen_modules
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'chosen_modules', 'xlsform_moddule_version_id', 'team_id');
    }



    // ** **** XLsform Components ********
    public function xlsfile(): Attribute
    {
        return new Attribute(
            get: fn(): string => $this->getFirstMediaPath('xlsform_file'),
        );
    }

    public function surveyRows(): HasMany
    {
        return $this->hasMany(SurveyRow::class);
    }

    public function choiceLists(): HasMany
    {
        return $this->hasMany(ChoiceList::class);
    }

    public function choiceListEntries(): HasManyThrough
    {
        return $this->hasManyThrough(ChoiceListEntry::class, ChoiceList::class, 'xlsform_module_version_id', 'choice_list_id', 'id', 'id');
    }

    public function locales(): BelongsToMany
    {
        return $this->belongsToMany(Locale::class, 'xlsform_module_version_locale', 'xlsform_module_version_id', 'locale_id')
            ->using(XlsformModuleVersionLocale::class)
            ->withPivot(['needs_update','has_language_strings']);
    }

    public function xlsformTemplateLanguages(): HasMany
    {
        return $this->hasMany(XlsformModuleVersionLocale::class);
    }

    // Split up language strings into 2 relationships
    public function surveyLanguageStrings(): HasManyThrough
    {
        return $this->hasManyThrough(LanguageString::class, SurveyRow::class, 'xlsform_module_version_id', 'linked_entry_id', 'id', 'id')
            ->where('language_strings.linked_entry_type', SurveyRow::class);
    }

    public function choiceListEntryLanguageStrings(): HasManyDeep
    {
        return $this->hasManyDeep(
            LanguageString::class,
            [ChoiceList::class, ChoiceListEntry::class],
            ['xlsform_module_version_id', 'choice_list_id', ['linked_entry_type', 'linked_entry_id']],
        );
    }
}
