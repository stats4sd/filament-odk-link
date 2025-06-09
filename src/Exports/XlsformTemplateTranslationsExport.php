<?php

namespace Stats4sd\FilamentOdkLink\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithBackgroundColor;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class XlsformTemplateTranslationsExport implements FromCollection, WithHeadings, WithTitle, WithColumnWidths, WithStyles, WithBackgroundColor
{

    /** @var Collection<Locale> */
    public Collection $locales;

    /** @var Collection<LanguageStringType> */
    public Collection $allLanguageStringTypes;

    public function __construct(public XlsformTemplate $template, public Locale $currentLocale, public bool $withExistingStrings = false)
    {

        $this->locales = $template->locales
            ->filter(fn(Locale $locale) => $locale->is_default);

        $this->allLanguageStringTypes = LanguageStringType::all();

    }

    public function headings(): array
    {
        // row  type
        // name
        // translation type
        // Locales
        // CurrentLocale

        $headings = collect();

        $headings[] = 'row type';
        $headings[] = 'choice_list_id';
        $headings[] = 'entry_id';
        $headings[] = 'name';
        $headings[] = 'translation type';

        $localeHeadings = $this->locales->pluck('language_label');

        $headings = $headings->merge($localeHeadings);

        $headings[] = $this->currentLocale->language_label;

        return $headings->toArray();

    }

    public function collection(): Collection
    {
        $surveyRows = $this->template->surveyRows
            ->sortBy('row_number')
            ->map(fn(SurveyRow $entry) => $this->processEntry($entry));

        $choiceListEntries = $this->template->choiceListEntries
            ->sortBy('row_number')
            ->map(fn(ChoiceListEntry $entry) => $this->processEntry($entry));

        return $surveyRows
            ->merge($choiceListEntries)
            ->filter()
            ->flatten(1);

    }

    public function backgroundColor(): string
    {
        // Adds fill to all rows/cols with no data
        return 'E7E7E7';

    }

    /**
     * @throws Exception
     */
    public function styles(Worksheet $sheet): void
    {
        // Define header style
        $h1 = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'B2D23E'],
            ],
        ];

        // Define orange fill style
        $orangeFill = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'cc8800'],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
        ];

        // Define white fill style for the last column (current template language)
        $whiteFill = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFFFFF'],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
        ];

        // Apply styles for the header row
        $lastColumnIndex = count($this->headings());
        $headingRange = 'A1:' . Coordinate::stringFromColumnIndex($lastColumnIndex) . '1';
        $sheet->getStyle($headingRange)->applyFromArray($h1);

        // Get total number of rows for styling
        $rowCount = $this->collection()->count() + 1; // +1 for the header row

        // Create a range for the data rows
        for ($rowIndex = 2; $rowIndex <= $rowCount; $rowIndex++) {
            // Apply orange fill to the entire row
            $dataRange = "A{$rowIndex}:" . Coordinate::stringFromColumnIndex($lastColumnIndex) . "{$rowIndex}";
            $sheet->getStyle($dataRange)->applyFromArray($orangeFill);

            // Apply white fill to the last column
            $sheet->getStyle(Coordinate::stringFromColumnIndex($lastColumnIndex) . "{$rowIndex}")->applyFromArray($whiteFill);
        }
    }

    public function columnWidths(): array
    {
        // Set specific widths for columns A and B
        $columnWidths = [
            'A' => 12,
            'B' => 0,
            'C' => 0,
            'D' => 29,
            'E' => 20,
        ];

        // Set width for all other columns
        $lastColumnIndex = count($this->headings());
        for ($i = 5; $i <= $lastColumnIndex; $i++) {
            $columnLetter = Coordinate::stringFromColumnIndex($i);
            $columnWidths[$columnLetter] = 45;
        }

        return $columnWidths;
    }

    public function title(): string
    {
        // Names the sheet
        return 'translations';
    }

    public function processEntry(SurveyRow|ChoiceListEntry $entry): Collection
    {

        // Get all language strings for this survey row grouped by type
        return $entry
            ->languageStrings
            ->groupBy('language_string_type_id')
            ->map(function (Collection $strings, $typeId) use ($entry): Collection {
                $languageStringType = $this->allLanguageStringTypes
                    ->filter(fn(LanguageStringType $languageStringType) => $languageStringType->id == $typeId)
                    ->first();

                // Create the initial row with the row type, 'name' and 'language string type'
                $type = $entry instanceof SurveyRow ? 'survey' : 'choices';
                $row = collect([
                    $type,
                    $entry->choiceList->id ?? '',
                    $entry->id,
                    $entry->name,
                    $languageStringType->name,
                ]);

                $defaultLocaleStrings = $this->template->locales
                    ->filter(fn(Locale $locale) => $locale->is_default)
                    ->map(function (Locale $locale) use ($strings): string {
                        // For each language in XlsformTemplateLanguage, add the corresponding text
                        // Find the language string for this language
                        $stringForLanguage = $strings->firstWhere('locale_id', $locale->id);

                        return $stringForLanguage ? $stringForLanguage->text : '';
                    });

                // Add the current template language's translation (unless $empty is false, which means we should return an empty template
                if ($this->withExistingStrings) {
                    $currentStringForLanguage = $strings->firstWhere('locale_id', $this->currentLocale->id);
                }

                return $row
                    ->merge($defaultLocaleStrings)
                    ->merge([$currentStringForLanguage->text ?? '']);
            });

    }
}
