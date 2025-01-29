<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages;

use App\Models\Team;
use App\Models\Xlsforms\Xlsform;
use App\Services\HelperService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class Locale extends Model
{

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected $appends = [
        'language_label',
    ];

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function xlsformModuleVersionLocales(): HasMany
    {
        return $this->hasMany(XlsformModuleVersionLocale::class);
    }

    public function xlsformModuleVersions(): BelongsToMany
    {
        return $this->belongsToMany(XlsformModuleVersion::class, 'xlsform_module_version_locale', 'locale_id', 'xlsform_module_version_id')
            ->using(XlsformModuleVersionLocale::class)
            ->withPivot(['has_language_strings', 'needs_update']);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'created_by_team_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'language_team', 'locale_id', 'team_id');
    }

    public function getLanguageLabelAttribute(): string
    {
        // if the description is populated, return that. Otherwise, return the language details
        return $this->description ?? $this->language->name . ' (default)';
    }

    public function getStatusAttribute(): string
    {
        $moduleVersions = $this->xlsformModuleVersions;
        $allModuleVersions = HelperService::getSelectedTeam()
            ->xlsforms
            ->map(fn(Xlsform $xlsform) => $xlsform
                ->xlsformTemplate
                ->xlsformModules
                ->map(fn(XlsformModule $xlsformModule) => $xlsformModule->defaultXlsformVersion)
            )->flatten();

        if ($moduleVersions->count() === 0) {
            return 'Not uploaded';
        }

        if ($moduleVersions->count() < $allModuleVersions->count()) {
            return 'Translations incomplete';
        }

        if ($moduleVersions->every(fn($moduleVersion) => !$moduleVersion->pivot->needs_update && $moduleVersion->pivot->has_language_strings)) {
            return 'Ready for use';
        }

        return 'Needs update';
    }

    public function getOdkLabelAttribute(): string
    {
        return $this->language->name . ' (' . $this->language->iso_alpha2 . ')';
    }


    // Are translations for this locale editable by the current team?
    public function getIsEditableAttribute(): bool
    {
        return $this->createdBy?->id === HelperService::getSelectedTeam()->id;
    }

    // Are translations for this locale being edited by the current team?
    public function getIsEditingAttribute(): bool
    {
        return $this->is_editable && $this->status !== 'Ready for use';
    }
}
