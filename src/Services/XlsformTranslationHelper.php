<?php

namespace Stats4sd\FilamentOdkLink\Services;

use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateHeadingRowImport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;

class XlsformTranslationHelper
{
    public Collection $languageStringTypes;

    public Collection $languages;

    public function __construct()
    {
        // make 1 set of db queries on instantiation
        $this->languageStringTypes = LanguageStringType::all();
        $this->languages = Language::all();
    }

    public function getLanguageStringTypeFromColumnHeader(string $columnHeader): LanguageStringType
    {
        logger('XlsformTranslationHelper.getLanguageStringTypeFromColumnHeader()...');

        preg_match($this->getRegexPattern(), $columnHeader, $matches);

        logger('matches:');
        logger($matches);
        logger(count($matches));

        // return $this->languageStringTypes->firstWhere('name', $matches[1]);

        if (count($matches) != 0) {
            logger('Locale found');
            $result = $this->languageStringTypes->firstWhere('name', $matches[1]);
        } else {
            logger('No locale found, always assume language string type is "label"');
            $result = $this->languageStringTypes->first();
        }

        return $result;
    }

    public function getLanguageFromColumnHeader(string $columnHeader): Language
    {
        logger('XlsformTranslationHelper.getLanguageFromColumnHeader()...');

        // TODO: check what language found from column header, return English if no language found from column heade4r

        preg_match($this->getRegexPattern(), $columnHeader, $matches);

        logger('matches:');
        logger($matches);
        logger(count($matches));

        // return $this->languages->firstWhere('iso_alpha2', $matches[3]);

        logger($this->languages);
        logger($this->languages->first());

        if (count($matches) != 0) {
            logger('Locale found');
            $result = $this->languages->firstWhere('iso_alpha2', $matches[3]);
        } else {
            logger('No locale found, use English as default locale');
            // $result = Language::where('iso_alpha2', 'en')->get();
            $result = $this->languages->first();
        }

        logger($result);

        return $result;
    }

    public function getTranslatableColumnsFromFile(string $filePath): Collection
    {
        logger('XlsformTranslationHelper.getTranslatableColumnsFromFile()...');

        // TODO: check if any translatable columns found, with class XlsformTemplateHeadingRowImport
        // if translatable columns is null, check again without matching locale two chars short code 

        // return a keyed collection for survey + choices headings.
        return (new XlsformTemplateHeadingRowImport)
            ->toCollection($filePath)
            ->mapWithKeys(fn($value, $key) => [
                $key => $value[0]
                    ->map(fn($columnHeader) => self::isTranslatableColumn($columnHeader) ? $columnHeader : null)
                    ->filter(),
            ]);
    }

    private function isTranslatableColumn(string $columnHeader): bool
    {
        // return preg_match($this->getRegexPattern(), $columnHeader);

        return true;
    }

    public function getRegexPattern(): string
    {
        $typeNames = $this->languageStringTypes->pluck('name')->toArray();
        $languageCodes = $this->languages->pluck('iso_alpha2')->toArray();

        return '/^(' . implode('|', $typeNames) . '):?:?([A-z]+)[_\s]\(?(' . implode('|', $languageCodes) . ')\)?$/';
    }


/* ********** */


    public function getLanguageFromColumnHeaderWithoutLanguage(string $columnHeader): Language
    {
        // preg_match($this->getRegexPatternWithoutLanguage(), $columnHeader, $matches);

        // return $this->languages->firstWhere('iso_alpha2', $matches[3]);

        // always return English
        // $defaultLanguage = Language::find('iso_alpha2', 'en')->get();
        $defaultLanguage = Language::find('iso_alpha2', 'en');

        return $defaultLanguage;

    }

    public function getTranslatableColumnsWithoutLanguageFromFile(string $filePath): Collection
    {
        logger('XlsformTranslationHelper.getTranslatableColumnsWithoutLanguageFromFile()...');

        // return a keyed collection for survey + choices headings.
        return (new XlsformTemplateHeadingRowImport)
            ->toCollection($filePath)
            ->mapWithKeys(fn($value, $key) => [
                $key => $value[0]
                    ->map(fn($columnHeader) => self::isTranslatableColumnWithoutLanguage($columnHeader) ? $columnHeader : null)
                    ->filter(),
            ]);
    }

    private function isTranslatableColumnWithoutLanguage(string $columnHeader): bool
    {
        return preg_match($this->getRegexPatternWithoutLanguage(), $columnHeader);
    }

    public function getRegexPatternWithoutLanguage(): string
    {
        $typeNames = $this->languageStringTypes->pluck('name')->toArray();

        return '/^(' . implode('|', $typeNames) . '):?:?([A-z]+)[_\s]?$/';
    }
}
