<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\HasLanguageStrings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\IsLookupList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Services\HelperService;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;
use Znck\Eloquent\Relations\BelongsToThrough;

class ChoiceListEntry extends Model implements HasLanguageStrings
{
    use IsLookupList;
    use \Znck\Eloquent\Traits\BelongsToThrough;

    protected $casts = [
        'is_localisable' => 'boolean',
        'is_dataset' => 'boolean',
        'properties' => 'collection',
        'updated_during_import' => 'boolean',
    ];

    protected static function booted(): void
    {
        // When Filament has tenancy enabled, we want to scope the choice list entries to the current tenant.
        static::addGlobalScope('owner', function (Builder $query) {

            if ($owner = HelperService::getCurrentOwner()) {

                $query->where('choice_list_entries.owner_id', $owner->getKey())
                    ->orWhereNull('choice_list_entries.owner_id');
            }
        });
    }

    /** @return BelongsTo<ChoiceList, $this> */
    public function choiceList(): BelongsTo
    {
        return $this->belongsTo(ChoiceList::class);
    }

    /** @return BelongsToThrough<XlsformModuleVersion, $this> */
    public function xlsformModuleVersion(): BelongsToThrough
    {
        return $this->belongsToThrough(
            XlsformModuleVersion::class,
            ChoiceList::class,
            foreignKeyLookup: [
                XlsformModuleVersion::class => 'xlsform_module_version_id',
                ChoiceList::class => 'choice_list_id',
            ],
        );
    }

    /** @return MorphMany<LanguageString, $this> */
    public function languageStrings(): MorphMany
    {
        return $this->morphMany(LanguageString::class, 'linked_entry');
    }

    /** @return MorphOne<LanguageString, $this> */
    public function defaultLabel(): MorphOne
    {
        return $this->morphOne(LanguageString::class, 'linked_entry')
            ->whereHas('language', fn($query) => $query->where('languages.iso_alpha2', 'en'))
            ->whereHas('languageStringType', fn($query) => $query->where('language_string_types.name', 'label'));
    }

    // Some choice lists are linked to specific data models to let us add custom information.

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Model, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('filament-odk-link.models.team_model'), 'owner_id');
    }

    /** @return BelongsToMany<Model, $this> */
    public function ownersWhoRemovedFromContext(): BelongsToMany
    {
        return $this->belongsToMany(config('filament-odk-link.models.team_model'), 'choice_list_entries_removed_owner', 'choice_list_entry_id', 'owner_id');
    }

    public function canBeHiddenFromContext(): bool
    {
        return $this->choiceList->can_be_hidden_from_context;
    }

    public function isRemoved(WithXlsforms $team): bool
    {
        return $team->choiceListEntriesRemovedFromContext->contains($this);
    }

    public function toggleRemoved(WithXlsforms $team): void
    {
        if ($this->isRemoved($team)) {
            $team->choiceListEntriesRemovedFromContext()->detach($this);
        } else {
            $team->choiceListEntriesRemovedFromContext()->attach($this);
        }
    }

    public function getLanguageString(string $type, Locale $locale): ?string
    {
        return $this->languageStrings()
            ->whereHas('languageStringType', fn($query) => $query->where('language_string_types.name', $type))
            ->whereHas('locale', fn(Builder $query) => $query->where('locales.id', $locale->id))
            ->first()?->text;
    }
}
