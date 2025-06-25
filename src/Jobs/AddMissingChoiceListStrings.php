<?php

namespace Stats4sd\FilamentOdkLink\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\XlsformTranslationHelper;

class AddMissingChoiceListStrings implements ShouldQueue
{
    use Queueable;


    public function __construct(public XlsformModuleVersion|XlsformTemplate $model)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        $choiceLists = $this->model->choiceLists()
            ->whereDoesntHave('choiceListEntries.languageStrings')
            ->get();

        $choiceLists->each(function (ChoiceList $choiceList) {

            $relationship = 'xlsformModuleVersion.xlsformModule.xlsformTemplate';

            if ($this->model instanceof XlsformModuleVersion) {
                $relationship = 'xlsformModuleVersion';
            }

            $matchingList = ChoiceList::where('list_name', $choiceList->list_name)
                ->whereHas($relationship, function (Builder $query) {
                    $query->where($this->model->getTable() . '.' . $this->model->getKeyName(), $this->model->getKey());
                })
                ->whereHas('choiceListEntries.languageStrings')
                ->with('choiceListEntries.languageStrings')
                ->first();

            ray($choiceList);
            ray($matchingList);


            $choiceList->choiceListEntries->each(function (ChoiceListEntry $choiceListEntry) use ($matchingList) {
                $matchingEntry = $matchingList->choiceListEntries
                    ->where('name', $choiceListEntry->name)
                    ->where('properties', $choiceListEntry->properties)
                    ->where('cascade_filter', $choiceListEntry->cascade_filter)
                    ->first();

                $stringsToAdd = $matchingEntry->languageStrings
                    ->map(function (LanguageString $languageString) use ($choiceListEntry) {
                        return new LanguageString([
                            'linked_entry_id' => $choiceListEntry->id,
                            'linked_entry_type' => ChoiceListEntry::class,
                            'locale_id' => $languageString->locale_id,
                            'language_string_type_id' => $languageString->language_string_type_id,
                            'text' => $languageString->text,
                        ]);
                    });

                $choiceListEntry->languageStrings()->insert($stringsToAdd->toArray());


            });


        });

    }
}
