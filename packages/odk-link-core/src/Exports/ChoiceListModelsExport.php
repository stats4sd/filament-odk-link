<?php

namespace Stats4sd\FilamentOdkLink\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsformDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;

class ChoiceListModelsExport implements FromCollection, WithHeadings, WithStrictNullComparison
{
    /** @var Collection<int, Collection<(int|string), mixed>> */
    public Collection $entries;

    // by default, we use the dataset variables as the columns. If you want to specify columns, you can pass them in as an array.
    public function __construct(
        public ChoiceList $choiceList,
        public WithXlsformDrafts|Xlsform $xlsform
    ) {

        /** @var WithXlsforms $owner */
        $owner = $xlsform->owner;

        $locales = $owner->locales;

        $this->entries = $choiceList->choiceListEntries
            // may not need explicit filter when running on front-end with Filament Tenancy, but won't hurt
            ->filter(fn (ChoiceListEntry $choiceListEntry) => $choiceListEntry->owner_id === $xlsform->owner->getKey() || $choiceListEntry->owner_id === null)
            ->mapWithKeys(function (ChoiceListEntry $choiceListEntry) use ($locales) {

                $labelColumns = $locales->mapWithKeys(function (Locale $locale) use ($choiceListEntry) {
                    return $choiceListEntry
                        ->languageStrings
                        ->filter(fn (LanguageString $languageString) => $languageString->locale->id === $locale->id)
                        ->mapWithKeys(fn (LanguageString $languageString) => [
                            "{$languageString->languageStringType->name}_{$locale->language->iso_alpha2}" => $languageString->text,
                        ]);
                });

                if (isset($choiceListEntry->choiceList->properties['extra_properties'])) {
                    $propertyColumns = collect($choiceListEntry->choiceList->properties['extra_properties'])->mapWithKeys(fn ($property) => [
                        $property['name'] => $choiceListEntry->properties[$property['name']] ?? null]);
                } else {
                    $propertyColumns = collect();
                }

                return [
                    $choiceListEntry->id => collect([
                        'id' => $choiceListEntry->id,
                        'name' => $choiceListEntry->name,
                        ...$labelColumns,
                        ...$propertyColumns,
                    ]),
                ];

            });
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
