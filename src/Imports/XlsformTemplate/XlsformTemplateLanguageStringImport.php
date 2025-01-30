<?php

namespace Stats4sd\FilamentOdkLink\Imports\XlsformTemplate;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Events\AfterImport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Services\XlsformTranslationHelper;

class XlsformTemplateLanguageStringImport implements ShouldQueue, SkipsEmptyRows, ToModel, WithChunkReading, WithEvents, WithHeadingRow, WithMultipleSheets, WithUpserts
{
    use Importable;
    use RegistersEventListeners;

    public XlsformTranslationHelper $xlsformTranslationHelper;

    public Language $language;

    public LanguageStringType $languageStringType;

    public ?string $class;

    public ?string $relationship;

    public function __construct(public XlsformModuleVersion $xlsformModuleVersion, public string $heading, public string $sheet)
    {

        // init translation helper and get needed props from the provided heading;
        $this->xlsformTranslationHelper = new XlsformTranslationHelper;
        $this->language = $this->xlsformTranslationHelper->getLanguageFromColumnHeader($this->heading);

        $this->languageStringType = $this->xlsformTranslationHelper->getLanguageStringTypeFromColumnHeader($this->heading);

        $this->relationship = match ($sheet) {
            'survey' => 'surveyRows',
            'choices' => 'choiceListEntries',
            default => null
        };

        $this->class = match ($sheet) {
            'survey' => SurveyRow::class,
            'choices' => ChoiceListEntry::class,
            default => null
        };

    }

    // Specify the "survey" sheet
    public function sheets(): array
    {
        if (! $this->class) {
            return []; // no actions if the class / worksheet is not recognised.
        }

        return [
            $this->sheet => $this,
        ];
    }

    public function isEmptyWhen(array $row): bool
    {
        return ! isset($row['name']) || $row['name'] === '';  // no need to import rows without a name, as we will never have translation strings for end_group or end_repeat rows.
    }

    public function uniqueBy(): array
    {
        return ['locale_id', 'linked_entry_id', 'linked_entry_type', 'language_string_type_id'];
    }

    public function model(array $row): ?LanguageString
    {
        $row = collect($row);

        $class = $this->class;
        $relationship = $this->relationship;

        $items = $this->xlsformModuleVersion->$relationship
            ->filter(fn ($item) => (string) $item->name === (string) $row['name']);

        // filter survey row entries by type as well as name
        if ($class === SurveyRow::class) {
            $items = $items
                ->filter(fn ($item) => (string) $item->type === (string) $row['type']);
        }

        // filter choice list entries by choice_list and every other property as well as name (as we can have 2 choice list entries in the same list with the same name, but different filters...)
        if ($class === ChoiceListEntry::class) {
            $items = $items
                ->filter(fn ($item) => (string) $item->choiceList->list_name === (string) $row['list_name'])
                ->filter(function (ChoiceListEntry $item) use ($row) {

                    // check each item property against the row
                    // every property must match the row input
                    return $item->properties
                        ->map(function ($value, $key) use ($row) {
                            return $row[$key] == $value;
                        })
                        ->every(fn ($value) => $value === true);
                });
        }

        $item = $items->first();

        if (! $item) {
            return null;
        }

        $translatableValue = $row
            ->filter(fn ($value, $key) => $this->heading === $key)
            ->filter(fn ($value, $key) => $value !== null && $value !== '')
            ->first();

        if (! $translatableValue) {
            return null;
        }

        // make sure the default locale for this language exists
        $locale = $this->language->locales()->firstOrCreate([
            'is_default' => true,
        ]);

        return new LanguageString([
            'linked_entry_id' => $item->id,
            'linked_entry_type' => $this->class,
            'locale_id' => $locale->id,
            'language_string_type_id' => $this->languageStringType->id,
            'text' => $row[$this->heading],
            'updated_during_import' => true,
        ]);

    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function afterImport(AfterImport $event): void
    {

        $type = match ($this->sheet) {
            'survey' => SurveyRow::class,
            'choices' => ChoiceListEntry::class,
            default => null
        };

        // find all Survey Rows linked to the XlsformTemplate that were not updated during the import... and delete them.
        $toDelete = $this->xlsformModuleVersion
            ->choiceListEntryLanguageStrings()
            ->where('language_string_type_id', $this->languageStringType->id)
            ->where('locale_id', $this->language->defaultLocale->id)
            ->where('linked_entry_type', $type)
            ->where('language_strings.updated_during_import', false)
            // don't delete items linked to customised entries, as the xlsformtemplate should never touch customised team entries.
            ->whereHasMorph('linkedEntry', ChoiceListEntry::class, function (Builder $query) {
                $query->where('owner_id', null);
            })
            ->get();

        $toDeleteToo = $this->xlsformModuleVersion
            ->surveyLanguageStrings()
            ->where('language_string_type_id', $this->languageStringType->id)
            ->where('locale_id', $this->language->defaultLocale->id)
            ->where('linked_entry_type', $type)
            ->where('language_strings.updated_during_import', false)
            ->whereHasMorph('linkedEntry', SurveyRow::class)
            ->get();

        $toDelete->each(fn (LanguageString $languageString) => $languageString->delete());
        $toDeleteToo->each(fn (LanguageString $languageString) => $languageString->delete());

    }
}
