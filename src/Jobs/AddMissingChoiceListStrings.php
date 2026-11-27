<?php

namespace Stats4sd\FilamentOdkLink\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\FilamentOdkLink\Concerns\ResetsProcessingOnFailure;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Stats4sd\FilamentOdkLink\Services\XlsformTranslationHelper;

class AddMissingChoiceListStrings implements ShouldQueue
{
    use Queueable;
    use ResetsProcessingOnFailure;


    public function __construct(public XlsformModuleVersion|XlsformTemplate $model)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->fillEntriesWithoutAnyString();
        $this->fillGlobalEntriesWithoutDefaultStrings();
    }

    private function fillEntriesWithoutAnyString(): void
    {
        $choiceLists = $this->model->choiceLists()
            ->whereHas('choiceListEntries', function (Builder $query) {
                $query->whereDoesntHave('languageStrings');
            })
            ->with('choiceListEntries.languageStrings')
            ->get();

        $choiceLists->each(function (ChoiceList $choiceList) {

            $relationship = 'xlsformModuleVersion.xlsformModule.xlsformTemplate';

            if ($this->model instanceof XlsformModuleVersion) {
                $relationship = 'xlsformModuleVersion';
            }

            $sourceLists = ChoiceList::where('list_name', $choiceList->list_name)
                ->where('id', '!=', $choiceList->id)
                ->whereHas($relationship, function (Builder $query) {
                    $query->where($this->model->getTable() . '.' . $this->model->getKeyName(), $this->model->getKey());
                })
                ->whereHas('choiceListEntries.languageStrings')
                ->with('choiceListEntries.languageStrings')
                ->get();

            if ($sourceLists->isEmpty()) {
                return;
            }

            $choiceList->choiceListEntries
                ->reject(fn (ChoiceListEntry $choiceListEntry) => $choiceListEntry->languageStrings->isNotEmpty())
                ->each(function (ChoiceListEntry $choiceListEntry) use ($sourceLists) {
                    $matchingEntry = $sourceLists
                        ->map(fn (ChoiceList $sourceList) => $sourceList->choiceListEntries
                            ->where('name', $choiceListEntry->name)
                            ->where('properties', $choiceListEntry->properties)
                            ->where('cascade_filter', $choiceListEntry->cascade_filter)
                            ->where('owner_id', $choiceListEntry->owner_id)
                            ->first(fn (ChoiceListEntry $candidate) => $candidate->languageStrings->isNotEmpty())
                        )
                        ->filter()
                        ->first();

                    if (! $matchingEntry) {
                        return;
                    }

                    $matchingEntry->languageStrings->each(fn (LanguageString $languageString) => $this->copyLanguageString($languageString, $choiceListEntry));
                });

        });

    }

    // Entries that kept their team translations but lost every default-locale string (the file import used to write a
    // string to the first list of a given name only). Only global entries of default module versions are repaired,
    // and only the default-locale strings of the donor are copied, so team translations are never touched.
    private function fillGlobalEntriesWithoutDefaultStrings(): void
    {
        $defaultVersionLists = $this->defaultVersionChoiceLists();

        if (! $defaultVersionLists) {
            return;
        }

        $choiceLists = $defaultVersionLists
            ->with(['choiceListEntries' => fn ($query) => $query
                ->whereNull('owner_id')
                ->with('languageStrings.locale'),
            ])
            ->orderBy('choice_lists.id')
            ->get();

        $choiceLists
            ->groupBy('list_name')
            ->filter(fn (Collection $sameNameLists) => $sameNameLists->count() > 1)
            ->each(function (Collection $sameNameLists) {
                $sameNameLists->each(function (ChoiceList $targetList) use ($sameNameLists) {
                    $donorLists = $sameNameLists->reject(fn (ChoiceList $candidate) => $candidate->is($targetList));

                    $targetList->choiceListEntries
                        ->reject(fn (ChoiceListEntry $entry) => $this->hasDefaultLocaleString($entry))
                        ->each(function (ChoiceListEntry $entry) use ($donorLists) {
                            $donorEntry = $donorLists
                                ->map(fn (ChoiceList $donorList) => $donorList->choiceListEntries->first(
                                    fn (ChoiceListEntry $candidate) => $this->isSameEntry($candidate, $entry)
                                        && $this->hasDefaultLocaleString($candidate)
                                ))
                                ->filter()
                                ->first();

                            if (! $donorEntry) {
                                return;
                            }

                            $donorEntry->languageStrings
                                ->filter(fn (LanguageString $languageString) => $languageString->locale?->is_default)
                                ->each(fn (LanguageString $languageString) => $this->copyLanguageString($languageString, $entry));
                        });
                });
            });
    }

    /** @return HasMany<ChoiceList, XlsformModuleVersion>|HasManyDeep<ChoiceList, XlsformTemplate>|null */
    private function defaultVersionChoiceLists(): ?Relation
    {
        if ($this->model instanceof XlsformModuleVersion && ! $this->model->is_default) {
            return null;
        }

        return $this->model->choiceListsForImport();
    }

    private function hasDefaultLocaleString(ChoiceListEntry $choiceListEntry): bool
    {
        return $choiceListEntry->languageStrings
            ->contains(fn (LanguageString $languageString) => (bool) $languageString->locale?->is_default);
    }

    private function isSameEntry(ChoiceListEntry $candidate, ChoiceListEntry $entry): bool
    {
        return (string) $candidate->name === (string) $entry->name
            && $candidate->properties == $entry->properties
            && $candidate->cascade_filter == $entry->cascade_filter;
    }

    private function copyLanguageString(LanguageString $languageString, ChoiceListEntry $choiceListEntry): void
    {
        $choiceListEntry->languageStrings()->firstOrCreate([
            'locale_id' => $languageString->locale_id,
            'language_string_type_id' => $languageString->language_string_type_id,
        ], [
            'text' => $languageString->text,
        ]);
    }
}
