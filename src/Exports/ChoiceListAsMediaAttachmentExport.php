<?php

namespace Stats4sd\FilamentOdkLink\Exports;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Stats4sd\FilamentOdkLink\Exports\XlsformExport\ExportsXlsformContent;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Abstracts\HasXlsformDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\RequiredMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;

class ChoiceListAsMediaAttachmentExport implements FromCollection, WithHeadings
{
    use ExportsXlsformContent;

    /** @var Collection<Locale> */
    public Collection $locales;

    /** @var \Illuminate\Support\Collection<string> */
    public \Illuminate\Support\Collection $propertyHeadings;

    /** @var Collection<ChoiceListEntry> */
    public Collection $choiceListEntries;

    public function __construct(public HasXlsformDrafts $xlsform, public RequiredMedia $requiredMedia)
    {
        ray('initialising csv export');
        $this->choiceListEntries = $this->requiredMedia->choiceList->getOwnedEntries($this->xlsform->owner);
        $this->locales = $xlsform->owner->locales;
        $this->propertyHeadings = $this->getHeadingsFromPropertyList($this->getHeadingsFromProperties());
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection(): \Illuminate\Support\Collection
    {
        return $this->choiceListEntries
            ->map(function (ChoiceListEntry $choiceListEntry) {
                return [
                    'choice_list_entry_id' => $choiceListEntry->id,
                    'name' => $choiceListEntry->name,
                    ...$this->getLanguageStrings($choiceListEntry, 'label'),
                    ...$this->mapPropertiesToPropertyHeadings($choiceListEntry),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'choice_list_entry_id',
            'name',
            ...$this->getLanguageStringHeaders('label'),
            ...$this->propertyHeadings->toArray(),
        ];
    }

    public function getHeadingsFromProperties(): \Illuminate\Support\Collection
    {
        // Run another SQL query to get the raw prop headings
        return ChoiceListEntry::selectRaw('json_keys(choice_list_entries.properties) as headings')
            ->whereNotNull('choice_list_entries.properties')
            ->whereIn('choice_list_entries.id', $this->choiceListEntries->pluck('id')->toArray())
            ->get();
    }
}
