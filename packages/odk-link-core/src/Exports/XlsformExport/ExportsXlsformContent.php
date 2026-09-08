<?php

namespace Stats4sd\FilamentOdkLink\Exports\XlsformExport;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;

trait ExportsXlsformContent
{
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
        return $this->locales
            ->mapWithKeys(function (Locale $locale) use ($row, $string) {
                $outputString = $this->expandMediaColumnHeaders($string);

                $key = "$outputString::{$locale->language->name} ({$locale->language->iso_alpha2})";
                $value = $row->languageStrings()
                    ->whereHas('locale', fn (Builder $query) => $query->where('locales.id', $locale->id))
                    ->whereHas('languageStringType', fn ($query) => $query->where('name', $string))
                    ->first()->text ?? '';

                return [$key => $value];
            });
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
            ->map(fn ($heading) => collect(json_decode($heading, true)))
            ->flatten()
            ->map(fn ($heading) => $this->expandMediaColumnHeaders($heading))
            ->unique()
            ->filter(fn ($heading) => ! Str::contains($heading, '::')); // remove any accidentally left-over language strings.
    }
}
