<?php

namespace Stats4sd\FilamentOdkLink\Exports\XlsformExport;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;

trait ExportsXlsformContent
{
    /** @var list<string> text string types that fall back to the default locale of the language */
    private const FALLBACK_STRING_TYPES = ['label', 'hint', 'required_message', 'relevant_message', 'constraint_message'];

    /** @var array<int, int|null> default locale id of the same language, by locale id */
    private array $defaultLocaleIds = [];

    /** @var array<string, int|null> */
    private array $stringTypeIds = [];

    public function mapPropertiesToPropertyHeadings(SurveyRow|ChoiceListEntry $entry): array
    {
        return $this->propertyHeadings->mapWithKeys(function (string $heading) use ($entry) {

            // formatting for media:: headings
            $key = Str::replace('::', '', $heading);

            return [$heading => $entry->properties[$key] ?? null];
        })->toArray();
    }

    public function getLanguageStringHeaders(string $string): Collection
    {
        return $this->locales
            ->map(function (Locale $locale) use ($string) {
                $outputString = $this->expandMediaColumnHeaders($string);
                return "$outputString::{$locale->language->name} ({$locale->language->iso_alpha2})";
            });
    }

    private function getLanguageStrings(SurveyRow|ChoiceListEntry $row, string $string): Collection
    {
        $stringTypeId = $this->stringTypeId($string);
        $canFallBack = in_array($string, self::FALLBACK_STRING_TYPES, true);

        return $this->locales
            ->mapWithKeys(function (Locale $locale) use ($row, $string, $stringTypeId, $canFallBack) {
                $outputString = $this->expandMediaColumnHeaders($string);

                $key = "$outputString::{$locale->language->name} ({$locale->language->iso_alpha2})";

                if (! $canFallBack) {
                    $value = $this->stringsOfType($row, $stringTypeId)
                        ->first(fn (LanguageString $languageString) => $languageString->locale_id === $locale->id)
                        ?->text ?? '';

                    return [$key => $value];
                }

                $value = $this->nonEmptyText($row, $stringTypeId, $locale->id);

                if ($value === '') {
                    $defaultLocaleId = $this->defaultLocaleIdFor($locale);
                    $value = $defaultLocaleId ? $this->nonEmptyText($row, $stringTypeId, $defaultLocaleId) : '';
                }

                return [$key => $value];
            });
    }

    /** The default locale of the locale's language, or null when the locale is that default (or the language has none). */
    private function defaultLocaleIdFor(Locale $locale): ?int
    {
        if (! array_key_exists($locale->id, $this->defaultLocaleIds)) {
            $this->defaultLocaleIds[$locale->id] = $locale->is_default
                ? null
                : Locale::where('language_id', $locale->language_id)->where('is_default', true)->value('id');
        }

        return $this->defaultLocaleIds[$locale->id];
    }

    private function nonEmptyText(SurveyRow|ChoiceListEntry $row, ?int $stringTypeId, int $localeId): string
    {
        $text = $this->stringsOfType($row, $stringTypeId)
            ->first(fn (LanguageString $languageString) => $languageString->locale_id === $localeId && trim((string) $languageString->text) !== '')
            ?->text;

        return $text ?? '';
    }

    /** @return Collection<int, LanguageString> */
    private function stringsOfType(SurveyRow|ChoiceListEntry $row, ?int $stringTypeId): Collection
    {
        return $row->languageStrings->filter(fn (LanguageString $languageString) => $languageString->language_string_type_id === $stringTypeId);
    }

    private function stringTypeId(string $name): ?int
    {
        if (! array_key_exists($name, $this->stringTypeIds)) {
            $this->stringTypeIds[$name] = LanguageStringType::where('name', $name)->value('id');
        }

        return $this->stringTypeIds[$name];
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

    // processes a list of property headings to return a filtered, flattened, unique list of them to be used as headers.
    private function getHeadingsFromPropertyList(Collection $entries): Collection
    {
        return $entries
            ->pluck('headings')
            ->filter() // remove null headings
            ->map(fn($heading) => collect(json_decode($heading, true)))
            ->flatten()
            ->map(fn($heading) => $this->expandMediaColumnHeaders($heading))
            ->unique()
            ->filter(fn($heading) => !Str::contains($heading, '::')); // remove any accidentally left-over language strings.
    }

}
