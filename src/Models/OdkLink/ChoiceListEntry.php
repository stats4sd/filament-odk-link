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
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithLanguageStrings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasLanguageStrings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\IsLookupList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Services\HelperService;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;
use Znck\Eloquent\Relations\BelongsToThrough;

class ChoiceListEntry extends Model implements WithLanguageStrings
{
    use IsLookupList;
    use \Znck\Eloquent\Traits\BelongsToThrough;
    use HasLanguageStrings;

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

        // When a choice list entry is updated, we need to recompile the drafts of any xlsforms that include it.
        static::saved(function (self $choiceListEntry) {
            $choiceListEntry->xlsformModuleVersion->xlsforms()->update([
                'draft_needs_update' => true,
            ]);

        });

        static::deleting(function (self $choiceListEntry) {
            $choiceListEntry->languageStrings->each(function (LanguageString $languageString) {
                $languageString->delete();
            });
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

}
