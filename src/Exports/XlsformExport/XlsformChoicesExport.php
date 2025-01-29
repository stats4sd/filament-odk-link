<?php

namespace Stats4sd\FilamentOdkLink\Exports\XlsformExport;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class XlsformChoicesExport implements FromCollection, ShouldAutoSize, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    /** @var Collection<Collection> */
    public Collection $choiceListRows;

    /** @var Collection<Locale> */
    public Collection $locales;

    public function __construct(public Xlsform $xlsform)
    {
        $this->locales = $xlsform->owner->locales;

        $xlsformModuleVersions = $this->xlsform->xlsformTemplate->xlsformModules()
            ->with('xlsformModuleVersions')
            ->orderBy('xlsform_modules.id')
            ->get()
            ->map(function (XlsformModule $module) {
                return $module->defaultXlsformVersion;
            });

        /** @var Collection<ChoiceListEntry> $choiceListEntries */
        $choiceListEntries = $xlsformModuleVersions->map(function (XlsformModuleVersion $xlsformModuleVersion) {
            return $xlsformModuleVersion
                ->choiceListEntries
                ->sortBy('id')
                ->load('languageStrings');
        })->flatten(1);

        $propertyHeadings = $this->getHeadingsFromProperties($choiceListEntries);

        $this->choiceListRows = $choiceListEntries
            ->map(function (ChoiceListEntry $row) use ($propertyHeadings) {

                $properties = $propertyHeadings->mapWithKeys(fn (string $heading) => [$heading => $row->properties[$heading] ?? null]);

                return collect([
                    'id' => $row->id,
                    'list_name' => $row->choiceList->list_name,
                    'name' => Str::snake($row->name),
                    ...$this->getLanguageStrings($row, 'label'),
                    ...$properties,
                ]);
            });

    }

    public function collection()
    {

        return $this->choiceListRows;
    }

    public function headings(): array
    {
        /** @var Collection $item */
        $item = $this->choiceListRows->first();

        return $item->keys()->toArray();
    }

    public function title(): string
    {
        return 'choices';
    }

    private function getHeadingsFromProperties(Collection $choiceListEntries): Collection
    {
        return $choiceListEntries
            ->map(fn ($choiceListEntry) => $choiceListEntry->properties?->keys())
            ->filter() // only non-nulls
            ->flatten()
            ->unique();
    }

    private function getLanguageStrings(mixed $row): Collection
    {
        $string = 'label';

        return $this->locales
            ->mapWithKeys(function (Locale $locale) use ($string, $row) {

                $key = "$string::{$locale->language->name} ({$locale->language->iso_alpha2})";
                $value = $row->languageStrings()
                    ->whereHas('languageStringType', fn ($query) => $query->where('name', $string))
                    ->first()?->text ?? null;

                return [$key => $value];
            });

    }

    public function styles(Worksheet $sheet): array
    {
        // starting at C, make 1 column auto-wrap per Xlsformtemplatelangauge
        $wrapArray = $this->locales->mapWithKeys(
            fn (Locale $locale, $index) => [chr(67 + $index) => ['alignment' => ['wrapText' => true]]]
        )->toArray();

        return [
            1 => ['font' => ['bold' => true]],
            ...$wrapArray,
        ];
    }

    public function columnWidths(): array
    {
        $labelColumns = $this->locales->mapWithKeys(fn (Locale $locale, $index) => [Coordinate::stringFromColumnIndex(4 + $index) => 60]);

        return [
            'A' => 5, // id
            'B' => 30, // list_name
            'C' => 30, // name
            ...$labelColumns->toArray(),
        ];
    }
}
