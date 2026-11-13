<?php

namespace Stats4sd\FilamentOdkLink\Exports\XlsformExport;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomQuerySize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class XlsformChoicesExport implements FromQuery, ShouldAutoSize, ShouldQueue, WithColumnWidths, WithCustomQuerySize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use ExportsXlsformContent;

    /** @var Collection<Locale> */
    public Collection $locales;

    /** @var Collection<string> */
    public Collection $propertyHeadings;

    /** @var array<string, list<int>>|null entry ids of each group of same-named entries; built on first use */
    private ?array $groupMemberIds = null;

    /** @var array<int, array<int, string>>|null non-empty label text by entry id, then locale id; built on first use */
    private ?array $labelIndex = null;

    public function __construct(public Xlsform $xlsform)
    {
        $this->locales = $xlsform->locale_list;

        $this->propertyHeadings = $this->getHeadingsFromPropertyList($this->getHeadingsFromProperties());
    }

    /**
     * Every entry that can appear in this form's choices sheet, before same-named duplicates are collapsed.
     *
     * @return Builder<ChoiceListEntry>
     */
    private function entriesInScope(): Builder
    {
        return ChoiceListEntry::query()
            ->leftJoinRelationship('choiceList')

            // only global entries and entries owned by the current form owner
            ->where(
                fn (Builder $query) => $query
                    ->where('choice_list_entries.owner_id', $this->xlsform->owner->getKey())
                    ->orWhere('choice_list_entries.owner_id', null)
            )

            // leave out entries the owner has removed from context (in any same-named list)
            ->notRemovedFor($this->xlsform->owner)

            // only entries in lists linked to a module version of the current form
            ->whereHas(
                'xlsformModuleVersion',
                fn (Builder $query) => $query
                    ->whereHas(
                        'xlsforms',
                        fn (Builder $query) => $query
                            ->where('xlsforms.id', $this->xlsform->id)
                    )
            );
    }

    public function query()
    {
        return $this->entriesInScope()
            // The representative of a group of same-named entries is the newest one that has a non-empty label;
            // blank placeholder rows created for new locales do not count as labelled.
            ->selectRaw(
                'coalesce(
                    max(case when exists (
                        select 1 from language_strings
                        join language_string_types on language_string_types.id = language_strings.language_string_type_id
                        where language_strings.linked_entry_type = ?
                        and language_strings.linked_entry_id = choice_list_entries.id
                        and language_string_types.name = ?
                        and language_strings.text is not null
                        and trim(language_strings.text) <> ?
                    ) then choice_list_entries.id end),
                    max(choice_list_entries.id)
                ) as id,
                max(choice_list_entries.choice_list_id) as choice_list_id,
                choice_lists.list_name,
                choice_list_entries.name,
                choice_list_entries.properties,
                choice_list_entries.cascade_filter',
                [ChoiceListEntry::class, 'label', '']
            )
            ->groupBy([
                'choice_lists.list_name',
                'choice_list_entries.name',
                'choice_list_entries.properties',
                'choice_list_entries.cascade_filter',
            ])
            ->with(['languageStrings', 'choiceList.xlsformModuleVersion.xlsforms'])
            ->orderBy('choice_lists.list_name')
            ->orderByRaw('min(case when choice_list_entries.owner_id is null then 0 else 1 end)')
            ->orderByRaw('min(choice_list_entries.id)');
    }

    /**
     * Labels are filled one locale at a time: a locale's label comes from the group's representative if it has one,
     * otherwise from any same-named duplicate that does. This stops a duplicate that only holds blank placeholder rows
     * from hiding the labels held by its siblings.
     *
     * @return array<string, string>
     */
    private function getChoiceLabels(ChoiceListEntry $row): array
    {
        $this->buildLabelIndex();

        $memberIds = $this->groupMemberIds[$this->groupKey($row->choiceList->list_name, $row)] ?? [];
        $memberIds = collect([$row->id, ...$memberIds])->unique()->values();

        return $this->locales
            ->mapWithKeys(function (Locale $locale) use ($memberIds) {
                $text = $memberIds
                    ->map(fn (int $id) => $this->labelIndex[$id][$locale->id] ?? '')
                    ->first(fn (string $text) => trim($text) !== '') ?? '';

                return ["label::{$locale->language->name} ({$locale->language->iso_alpha2})" => $text];
            })
            ->all();
    }

    private function groupKey(string $listName, ChoiceListEntry $entry): string
    {
        return json_encode([$listName, $entry->name, $entry->properties, $entry->cascade_filter]);
    }

    private function buildLabelIndex(): void
    {
        if ($this->labelIndex !== null) {
            return;
        }

        $this->groupMemberIds = [];
        $this->labelIndex = [];

        $entries = $this->entriesInScope()
            ->select([
                'choice_list_entries.id',
                'choice_lists.list_name',
                'choice_list_entries.name',
                'choice_list_entries.properties',
                'choice_list_entries.cascade_filter',
            ])
            ->orderByDesc('choice_list_entries.id')
            ->get();

        foreach ($entries as $entry) {
            $this->groupMemberIds[$this->groupKey($entry->getAttribute('list_name'), $entry)][] = $entry->id;
        }

        foreach ($entries->pluck('id')->chunk(1000) as $ids) {
            DB::table('language_strings')
                ->join('language_string_types', 'language_string_types.id', '=', 'language_strings.language_string_type_id')
                ->where('language_strings.linked_entry_type', ChoiceListEntry::class)
                ->whereIn('language_strings.linked_entry_id', $ids)
                ->where('language_string_types.name', 'label')
                ->whereNotNull('language_strings.text')
                ->get(['language_strings.linked_entry_id', 'language_strings.locale_id', 'language_strings.text'])
                ->each(function ($string) {
                    if (trim($string->text) !== '') {
                        $this->labelIndex[$string->linked_entry_id][$string->locale_id] = $string->text;
                    }
                });
        }
    }

    public function map($row): array
    {
        return [
            'list_name' => $row->choiceList->list_name,
            'name' => Str::replace(' ', '_', $row->name),
            ...$this->getChoiceLabels($row),
            ...$this->mapPropertiesToPropertyHeadings($row),
        ];
    }

    public function headings(): array
    {
        return [
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
            'A' => 30, // list_name
            'B' => 30, // name
            ...$labelColumns->toArray(),
        ];
    }

    public function getHeadingsFromProperties(): Collection
    {
        return $this->xlsform->choiceListEntries()
            ->selectRaw('json_keys(choice_list_entries.properties) as headings')
            ->whereNotNull('choice_list_entries.properties')
            ->get();
    }

    public function querySize(): int
    {
        if ($this->xlsform->xlsformModuleVersions) {

            return $this->xlsform->xlsformModuleVersions->map(function (XlsformModuleVersion $xlsformModuleVersion) {

                return $xlsformModuleVersion->choiceListEntries()->count() ?? 0;
            })->reduce(fn (?int $carry, int $count) => $carry + $count, 0);
        }
    }
}
