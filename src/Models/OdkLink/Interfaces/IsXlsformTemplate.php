<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces;

use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\RequiredMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplateSection;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;

interface IsXlsformTemplate
{
    public function afterXlsformFileUpdated(): void;

    public function registerMediaCollections(): void;

    /** @return HasManyThrough<Submission, Xlsform, $this> */
    public function submissions(): HasManyThrough;

    /** @return HasMany<Xlsform, $this> */
    public function xlsforms(): HasMany;

    /** @return HasMany<Xlsform, $this> */
    public function activeXlsforms(): HasMany;

    public function owner(): MorphTo;

    /** 1 entry created for each required item as given from ODK Central */
    /** @return HasMany<RequiredMedia, $this> */
    public function requiredMedia(): HasMany;


    /** filtered Required Media to only show media with type "image", "video" or "audio" */
    /** @return HasMany<RequiredMedia, $this> */
    public function requiredFixedMedia(): HasMany;


    /** @return HasMany<RequiredMedia, $this> */
    public function requiredDataMedia(): HasMany;


    /** @return HasMany<RequiredMedia, $this> */
    public function attachedFixedMedia(): HasMany;


    /** @return HasMany<RequiredMedia, $this> */
    public function attachedDataMedia(): HasMany;


    /** @return BelongsToMany<Dataset, $this> */
    public function datasets(): BelongsToMany;


    /** @return HasMany<XlsformTemplateSection, $this> */
    public function xlsformTemplateSections(): HasMany;

    /** @return HasMany<XlsformTemplateSection, $this> */
    public function repeatingSections(): HasMany;


    /** @return HasOne<XlsformTemplateSection, $this> */
    public function rootSection(): HasOne;


    /** @return HasMany<XlsformModule, $this> */
    public function xlsformModules(): HasMany;


    /** @return HasManyDeep<SurveyRow, $this> */
    public function surveyRows(): HasManyDeep;


    /** @return HasManyDeep<ChoiceList, $this> */
    public function choiceLists(): HasManyDeep;


    /** @return HasManyDeep<ChoiceListEntry, $this> */
    public function choiceListEntries(): HasManyDeep;


    // Split up language strings into 2 relationships as there are 2 paths between xlsformtemplates and language strings

    /** @return HasManyDeep<LanguageString, $this> */
    public function surveyLanguageStrings(): HasManyDeep;


    /** @return HasManyDeep<LanguageString, $this> */
    public function choiceListEntryLanguageStrings(): HasManyDeep;

    // ****************** METHODS ************************

    // get required media from ODK Central and store in the database
    public function getRequiredMedia(): void;

    // get link to form in ODK Central
    public function getOdkLinkAttribute(): ?string;

    /** @return Collection<XlsformTemplateSection> */
    public function extractSections(): Collection;

    // mark all xlsforms using this template as not current (i.e. not using the latest template)
    public function markAllAsNotCurrent(): void;


    public function testOnOdkCentral(): self;

}
