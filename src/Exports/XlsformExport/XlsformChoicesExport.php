<?php

namespace Stats4sd\FilamentOdkLink\Exports\XlsformExport;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class XlsformChoicesExport implements FromQuery, ShouldAutoSize, WithColumnWidths, WithHeadings, WithStyles, WithTitle, ShouldQueue, WithMapping
{

    use ExportsXlsformContent;

    /** @var Collection<Locale> */
    public Collection $locales;

    /** @var Collection<string> */
    public Collection $propertyHeadings;

    public function __construct(public Xlsform $xlsform)
    {
        $this->locales = $xlsform->owner->locales;
        $this->propertyHeadings = $this->getHeadingsFromProperties('choiceListEntries');
    }

    public function query()
    {
        return ChoiceListEntry::query()
            ->leftJoinRelationship('xlsformModuleVersion.xlsforms')
            ->select([
                'choice_list_entries.id',
                'choice_list_entries.list_name',
                'choice_list_entries.name',
                'choice_list_entries.properties',
                'choice_list_entries.cascade_filter',
            ])
            ->distinct()

            // only global entries and entries owned by the current form owner
            ->where(fn(Builder $query) => $query
                ->where('owner_id', $this->xlsform->owner->getKey())
                ->orWhere('owner_id', null)
            )

            // only entries in lists linked to a module version of the current form
            ->whereHas('xlsformModuleVersion', fn(XlsformModuleVersion $query) => $query->whereHas('xlsforms', fn(Xlsform $query) => $query->where('xlsforms.id', $this->xlsform->id)))
            ->with(['languageStrings', 'xlsformModuleVersion.xlsforms'])
            ->orderBy('choice_list_entries.list_name')
            ->orderBy('choice_list_entries.name');
    }

    public function map($row): array
    {
        return [
            'id' => $row->id,
            'list_name' => $row->list_name,
            'name' => Str::replace(' ', '_', $row->name),
            ...$this->getLanguageStrings($row),
            ...$this->mapPropertiesToPropertyHeadings($row, 'label'),
        ];
    }

    public function headings(): array
    {
        return [
            'id',
            'list_name',
            'name',
            ...$this->getLanguageStringHeaders('label'),
            ...$this->propertyHeadings->toArray(),
        ];
    }

    public function expandMediaColumnHeaders(string $string): string
    {
        // fix for mediaimage needing to be media::image, etc.

        if ($string === 'mediaimage') {
            return 'media::image';
        }

        if ($string === 'mediaaudio') {
            return 'media::audio';
        }

        if ($string === 'mediavideo') {
            return 'media::video';
        }

        return $string;
    }

    public function title(): string
    {
        return 'choices';
    }


    public function styles(Worksheet $sheet): array
    {
        // starting at C, make 1 column auto-wrap per Xlsformtemplatelangauge
        $wrapArray = $this->locales->mapWithKeys(
            fn(Locale $locale, $index) => [chr(67 + $index) => ['alignment' => ['wrapText' => true]]]
        )->toArray();

        return [
            1 => ['font' => ['bold' => true]],
            ...$wrapArray,
        ];
    }

    public function columnWidths(): array
    {
        $labelColumns = $this->locales->mapWithKeys(fn(Locale $locale, $index) => [Coordinate::stringFromColumnIndex(4 + $index) => 60]);

        return [
            'A' => 5, // id
            'B' => 30, // list_name
            'C' => 30, // name
            ...$labelColumns->toArray(),
        ];
    }


}
