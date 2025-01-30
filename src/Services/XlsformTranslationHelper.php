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
        preg_match($this->getRegexPattern(), $columnHeader, $matches);

        return $this->languageStringTypes->firstWhere('name', $matches[1]);
    }

    public function getLanguageFromColumnHeader(string $columnHeader): Language
    {
        preg_match($this->getRegexPattern(), $columnHeader, $matches);

        return $this->languages->firstWhere('iso_alpha2', $matches[3]);
    }

    public function getRegexPattern(): string
    {
        $typeNames = $this->languageStringTypes->pluck('name')->toArray();
        $languageCodes = $this->languages->pluck('iso_alpha2')->toArray();

        return '/^(' . implode('|', $typeNames) . ')([a-z]+)_(' . implode('|', $languageCodes) . ')$/';
    }
}
