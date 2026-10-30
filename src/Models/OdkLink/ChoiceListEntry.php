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

    protected $guarded = [];

    protected static function booted(): void
    {
        // When Filament has tenancy enabled, we want to scope the choice list entries to the current tenant.
        static::addGlobalScope('owner', function (Builder $query) {

            if ($owner = HelperService::getCurrentOwner()) {

                $query->where(function(Builder $query) use ($owner) {
                    $query->where('choice_list_entries.owner_id', $owner->getKey())
                    ->orWhereNull('choice_list_entries.owner_id');
                });
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
        return $this->belongsTo(config('filament-odk-link.models.form_owner'), 'owner_id');
    }

    /** @return BelongsToMany<Model, $this> */
    public function ownersWhoRemovedFromContext(): BelongsToMany
    {
        return $this->belongsToMany(config('filament-odk-link.models.form_owner'), 'choice_list_entries_removed_owner', 'choice_list_entry_id', 'owner_id');
    }

    /**
     * Exclude entries the owner has removed from context.
     *
     * The same list_name can exist once per module version and teams manage the copies as one list,
     * so an entry counts as removed when the owner removed ANY entry with the same list_name and name.
     *
     * @param  Builder<ChoiceListEntry>  $query
     */
    public function scopeNotRemovedFor(Builder $query, WithXlsforms $owner): void
    {
        $query->whereNotExists(function ($removedQuery) use ($owner) {
            $removedQuery->selectRaw('1')
                ->from('choice_list_entries_removed_owner as removed')
                ->join('choice_list_entries as removed_entry', 'removed_entry.id', '=', 'removed.choice_list_entry_id')
                ->join('choice_lists as removed_list', 'removed_list.id', '=', 'removed_entry.choice_list_id')
                ->join('choice_lists as own_list', 'own_list.list_name', '=', 'removed_list.list_name')
                ->where('removed.owner_id', $owner->getKey())
                ->whereColumn('own_list.id', 'choice_list_entries.choice_list_id')
                ->whereColumn('removed_entry.name', 'choice_list_entries.name');
        });
    }

    public function canBeHiddenFromContext(): bool
    {
        return $this->choiceList->can_be_hidden_from_context;
    }

    /**
     * Same rule as scopeNotRemovedFor(): the entry counts as removed when the team removed
     * any entry with the same list_name and name, so the UI matches the generated form.
     */
    public function isRemoved(WithXlsforms $team): bool
    {
        $this->loadMissing('choiceList');

        return $team->choiceListEntriesRemovedFromContext
            ->loadMissing('choiceList')
            ->contains(fn (ChoiceListEntry $removed) => $removed->name === $this->name
                && $removed->choiceList->list_name === $this->choiceList->list_name);
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
