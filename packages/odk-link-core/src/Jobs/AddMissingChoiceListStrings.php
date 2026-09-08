<?php

namespace Stats4sd\FilamentOdkLink\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\FilamentOdkLink\Concerns\ResetsProcessingOnFailure;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class AddMissingChoiceListStrings implements ShouldQueue
{
    use Queueable;
    use ResetsProcessingOnFailure;

    public function __construct(public XlsformModuleVersion|XlsformTemplate $model) {}

    /**
     * Execute the job.
     */
    public function handle(): void
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

            $matchingList = ChoiceList::where('list_name', $choiceList->list_name)
                ->whereHas($relationship, function (Builder $query) {
                    $query->where($this->model->getTable().'.'.$this->model->getKeyName(), $this->model->getKey());
                })
                ->whereHas('choiceListEntries.languageStrings')
                ->with('choiceListEntries.languageStrings')
                ->first();

            if (! $matchingList) {
                return;
            }

            $choiceList->choiceListEntries
                ->reject(fn (ChoiceListEntry $choiceListEntry) => $choiceListEntry->languageStrings->isNotEmpty())
                ->each(function (ChoiceListEntry $choiceListEntry) use ($matchingList) {
                    $matchingEntry = $matchingList->choiceListEntries
                        ->where('name', $choiceListEntry->name)
                        ->where('properties', $choiceListEntry->properties)
                        ->where('cascade_filter', $choiceListEntry->cascade_filter)
                        ->first();

                    if (! $matchingEntry) {
                        return;
                    }

                    $matchingEntry->languageStrings->each(function (LanguageString $languageString) use ($choiceListEntry) {
                        $choiceListEntry->languageStrings()->updateOrCreate([
                            'locale_id' => $languageString->locale_id,
                            'language_string_type_id' => $languageString->language_string_type_id,
                        ], [
                            'text' => $languageString->text,
                        ]);
                    });
                });

        });

    }
}
