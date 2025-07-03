<?php

namespace Stats4sd\FilamentOdkLink\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\RequiredMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class FinishChoiceListEntryImport implements ShouldQueue
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
        // If choice list entries are localisable, make sure the whole ChoiceList is too
        $localisableChoiceLists = $this->model->choiceLists()
            ->whereHas('choiceListEntries', function (Builder $query) {
                $query->whereLike('properties', '%localisable": true%');
            })
            // IGNORE choice lists linked to the location module, as they are handled separately
            ->whereHas('xlsformModuleVersion.xlsformModule', function (Builder $query) {
                $query->where('name', '!=', 'location');
            })->with('choiceListEntries')
            ->get();



        $localisableChoiceLists
            ->each(function (ChoiceList $choiceList) {

                // If choice list entries have extra properties, make sure they are added as 'extra_properties' in the choice list, so users can add them to localised entries too
                $extraProperties = $choiceList->choiceListEntries
                    ->map(function (ChoiceListEntry $choiceListEntry) {
                        return $choiceListEntry->properties->keys();
                    })
                    ->flatten()
                    ->unique()
                    ->filter(fn(string $key) => $key !== 'localisable');

                $properties = $choiceList->properties;
                $properties['extra_properties'] = $extraProperties->map(function (string $property) use ($choiceList) {

                    // Custom values for "units"
                    if (
                        Str::contains($property, 'conversion_rate') &&
                        Str::contains($choiceList->list_name, '_unit')
                    ) {
                        return [
                            'name' => 'conversion_rate',
                            'label' => 'Conversion Rate (1 S.I. unit = ?? of this unit)',
                            'hint' => 'This value will be used in the calculations built into the ODK form, so please make sure you enter only the number (it can be a decimal with any number of decimal places depending on the required accuracy)',
                        ];
                    }

                    return [
                        'name' => $property,
                        'label' => Str::title($property),
                        'hint' => '',
                    ];
                });

                $choiceList->update([
                    'is_localisable' => true,
                    'properties' => $properties,
                ]);

                // some localisable choice lists also need to be compiled as csv files for pulldata().
                // these should be identifiable because the RequiredMedia name is ${list_name}_info.csv.

                $matchedMedia = $this->model->requiredDataMedia()
                    ->where('name', $choiceList->list_name . '_info.csv')
                    ->first();

                if ($matchedMedia instanceof RequiredMedia) {
                    $matchedMedia->choiceList()->associate($choiceList);
                    $matchedMedia->save();
                }

            });

        $this->model->choiceListEntries()
            ->update(['updated_during_import' => false]);

    }
}
