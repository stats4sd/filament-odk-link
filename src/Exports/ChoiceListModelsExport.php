<?php

namespace Stats4sd\FilamentOdkLink\Exports;

use App\Models\Locale;
use App\Models\Xlsforms\Xlsform;
use App\Models\XlsformTemplateLanguage;
use App\Models\XlsformTemplates\ChoiceList;
use App\Models\XlsformTemplates\ChoiceListEntry;
use App\Models\XlsformTemplates\LanguageString;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsFormDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Models\TeamManagement\Team;

class ChoiceListModelsExport implements FromCollection, WithHeadings, WithStrictNullComparison
{

    public Collection $entries;

    // by default, we use the dataset variables as the columns. If you want to specify columns, you can pass them in as an array.
    public function __construct(
        public ChoiceList                $choiceList,
        public WithXlsFormDrafts|Xlsform $xlsform)
    {

        // get the template languages for the form chosen by the team
        $xlsformTemplateLanguages = $xlsform->xlsformTemplate->xlsformTemplateLanguages
            ->filter(fn(XlsformTemplateLanguage $xlsformTemplateLanguage) => $xlsform->owner->locales->contains('id', $xlsformTemplateLanguage->locale->id));

        $this->entries = $choiceList->choiceListEntries
            // may not need explicit filter when running on front-end with Filament Tenancy, but won't hurt
            ->filter(fn(ChoiceListEntry $choiceListEntry) => $choiceListEntry->owner_id === $xlsform->owner->id || $choiceListEntry->owner_id === null)
            ->mapWithKeys(function (ChoiceListEntry $choiceListEntry) use ($xlsformTemplateLanguages) {

                ray('processing ' . $choiceListEntry->name);

                $labelColumns = $xlsformTemplateLanguages->mapWithKeys(function (XlsformTemplateLanguage $xlsformTemplateLanguage) use ($choiceListEntry) {
                    return $choiceListEntry
                        ->languageStrings
                        ->filter(fn(LanguageString $languageString) => $languageString->xlsformTemplateLanguage->id === $xlsformTemplateLanguage->id)
                        ->mapWithKeys(fn(LanguageString $languageString) => [
                            "{$languageString->languageStringType->name}_{$xlsformTemplateLanguage->language->iso_alpha2}" => $languageString->text,
                        ]);
                });

                $propertyColumns = collect($choiceListEntry->choiceList->properties['extra_properties'])->mapWithKeys(fn($property) => [
                    $property['name'] => $choiceListEntry->properties[$property['name']] ?? null]);


                return [
                    $choiceListEntry->id => collect([
                        'id' => $choiceListEntry->id,
                        'name' => $choiceListEntry->name,
                        ...$labelColumns,
                        ...$propertyColumns,
                    ]),
                ];

            })
            ->values();
    }

    public function collection(): Collection
    {
        return $this->entries;
    }

    public function headings(): array
    {
        return $this->entries->first()->keys()->toArray();
    }
}
