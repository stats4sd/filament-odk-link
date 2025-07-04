<?php

namespace Stats4sd\FilamentOdkLink\Exports\XlsformExport;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;

class XlsformSurveyExport implements FromQuery, ShouldAutoSize, ShouldQueue, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use ExportsXlsformContent;

    /** @var Collection<Locale> */
    public Collection $locales;

    /** @var Collection<string> */
    public Collection $propertyHeadings;

    public function __construct(public Xlsform $xlsform)
    {
        $this->locales = $xlsform->locale_list;

        $this->propertyHeadings = $this->getHeadingsFromPropertyList($this->getHeadingsFromProperties());

    }

    public function query(): Builder
    {
        $surveyRowColumns = collect(
            DB::connection()
                ->getSchemaBuilder()
                ->getColumnListing((new SurveyRow)->getTable())
        )
            ->map(fn ($column) => "survey_rows.$column")
            ->toArray();

        return SurveyRow::query()
            ->leftJoinRelationship('xlsformModuleVersion.xlsforms')
            ->select([
                ...$surveyRowColumns,
                'selected_xlsform_module_versions.order',
                'selected_xlsform_module_versions.xlsform_module_version_id',
            ])
            ->whereRaw('selected_xlsform_module_versions.xlsform_id = '.$this->xlsform->id)
            ->with(['languageStrings', 'xlsformModuleVersion.xlsforms'])
            ->distinct()
            ->orderBy('selected_xlsform_module_versions.order')
            ->orderBy('selected_xlsform_module_versions.xlsform_module_version_id')
            ->orderBy('row_number');
    }

    /** @param SurveyRow $surveyRow */
    public function map($surveyRow): array
    {
        return [
            'id' => $surveyRow->id,
            'row_number' => $surveyRow->row_number,
            'type' => $surveyRow->type_and_choice_list,
            'name' => $surveyRow->name,
            ...$this->getLanguageStrings($surveyRow, 'label'),
            ...$this->getLanguageStrings($surveyRow, 'hint'),
            'required' => $surveyRow->required,
            ...$this->getLanguageStrings($surveyRow, 'required_message'),
            'calculation' => $surveyRow->calculation,
            'relevant' => $surveyRow->relevant,
            ...$this->getLanguageStrings($surveyRow, 'relevant_message'),
            'appearance' => $surveyRow->appearance,
            'constraint' => $surveyRow->constraint,
            ...$this->getLanguageStrings($surveyRow, 'constraint_message'),
            'choice_filter' => $surveyRow->choice_filter,
            'repeat_count' => $surveyRow->repeat_count,
            ...$this->getLanguageStrings($surveyRow, 'mediaimage'),
            'default' => $surveyRow->default,
            ...$this->mapPropertiesToPropertyHeadings($surveyRow),
        ];
    }

    public function headings(): array
    {
        return [
            'id',
            'row_number',
            'type',
            'name',
            ...$this->getLanguageStringHeaders('label'),
            ...$this->getLanguageStringHeaders('hint'),
            'required',
            ...$this->getLanguageStringHeaders('required_message'),
            'calculation',
            'relevant',
            ...$this->getLanguageStringHeaders('relevant_message'),
            'appearance',
            'constraint',
            ...$this->getLanguageStringHeaders('constraint_message'),
            'choice_filter',
            'repeat_count',
            ...$this->getLanguageStringHeaders('mediaimage'),
            'default',
            ...$this->propertyHeadings->toArray(),
        ];
    }

    public function title(): string
    {
        return 'survey';
    }

    public function columnWidths(): array
    {
        $languageCount = $this->locales->count();

        $labelColumns = $this->locales->mapWithKeys(fn (Locale $locale, $index) => [Coordinate::stringFromColumnIndex(4 + $index) => 45]);
        $hintColumns = $this->locales->mapWithKeys(fn (Locale $locale, $index) => [Coordinate::stringFromColumnIndex(4 + $languageCount + $index) => 45]);

        return [
            'A' => 5, // ID
            'B' => 20, // type
            'C' => 30, // name
            ...$labelColumns->toArray(),
            ...$hintColumns->toArray(),
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 1) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 2) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 3) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 4) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 5) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 6) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 7) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 8) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 9) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 10) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 11) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 12) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 13) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 14) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 15) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 16) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 17) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 18) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 19) => 15,
            Coordinate::stringFromColumnIndex(4 + $languageCount * 2 + 20) => 15,
        ];
    }

    /**
     * @throws Exception
     */
    public function styles(Worksheet $sheet): void
    {
        $languageCount = $this->locales->count();

        $wrapStyle = ['alignment' => ['wrapText' => true]];

        $beginGroupStyle = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '8ED873'],
                'endColor' => ['rgb' => '8ED873'],
            ],
        ];

        $endGroupStyle = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FA8E78'],
                'endColor' => ['rgb' => 'FA8E78'],
            ],
        ];

        $beginRepeatStyle = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '83CAEB'],
                'endColor' => ['rgb' => '83CAEB'],
            ],
        ];

        $endRepeatStyle = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E49EDD'],
                'endColor' => ['rgb' => 'E49EDD'],
            ],
        ];

        // starting at C, make label + hint columns auto-wrap per Xlsformtemplatelangauge
        $wrapLabelList = $this->locales->map(fn (Locale $language, $index) => chr(67 + $index));
        $wrapHintList = $this->locales->map(fn (Locale $language, $index) => chr(67 + $languageCount + $index));

        // **** APPLY STYLES ****
        $dynamicStylesRowLists = $this->getDynamicStylesRowLists($sheet);

        $sheet->getStyle('1:1')->getFont()->setBold(true);

        foreach ($wrapLabelList as $column) {
            $sheet->getStyle($column.':'.$column)->applyFromArray($wrapStyle);
        }

        foreach ($wrapHintList as $column) {
            $sheet->getStyle($column.':'.$column)->applyFromArray($wrapStyle);
        }

        foreach ($dynamicStylesRowLists['beginGroupRows'] as $row) {
            $sheet->getStyle($row.':'.$row)->applyFromArray($beginGroupStyle);
        }

        foreach ($dynamicStylesRowLists['endGroupRows'] as $row) {
            $sheet->getStyle($row.':'.$row)->applyFromArray($endGroupStyle);
        }

        foreach ($dynamicStylesRowLists['beginRepeatRows'] as $row) {
            $sheet->getStyle($row.':'.$row)->applyFromArray($beginRepeatStyle);
        }

        foreach ($dynamicStylesRowLists['endRepeatRows'] as $row) {
            $sheet->getStyle($row.':'.$row)->applyFromArray($endRepeatStyle);
        }

    }

    private function getDynamicStylesRowLists(Worksheet $sheet): Collection
    {
        $rows = $sheet->toArray();

        $beginGroupRows = collect($rows)->filter(fn (array $row) => $row[2] === 'begin_group')->keys();
        $endGroupRows = collect($rows)->filter(fn (array $row) => $row[2] === 'end_group')->keys();
        $beginRepeatRows = collect($rows)->filter(fn (array $row) => $row[2] === 'begin_repeat')->keys();
        $endRepeatRows = collect($rows)->filter(fn (array $row) => $row[2] === 'end_repeat')->keys();

        return collect([
            'beginGroupRows' => $beginGroupRows->map(fn ($id) => $id + 1),
            'endGroupRows' => $endGroupRows->map(fn ($id) => $id + 1),
            'beginRepeatRows' => $beginRepeatRows->map(fn ($id) => $id + 1),
            'endRepeatRows' => $endRepeatRows->map(fn ($id) => $id + 1),
        ]);
    }

    public function getHeadingsFromProperties(): Collection
    {
        return $this->xlsform->surveyRows()
            ->selectRaw('json_keys(survey_rows.properties) as headings')
            ->whereNotNull('survey_rows.properties')
            ->get();
    }
}
