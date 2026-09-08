<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Services\XlsformTranslationHelper;

/** @property Collection $properties */
trait HasLanguageStrings
{
    protected static function bootHasLanguageStrings(): void
    {
        static::saved(function (self $entity) {

            $entity->properties?->keys()
                ->filter(fn (string $key) => Str::contains($key, '::'))
                ->each(function (string $key) use ($entity) {

                    $xlsformTranslationHelper = app()->make(XlsformTranslationHelper::class);

                    $stringType = $xlsformTranslationHelper->getLanguageStringTypeFromColumnHeader($key);
                    $language = $xlsformTranslationHelper->getLanguageFromColumnHeader($key);

                    $entity->languageStrings()->updateOrCreate([
                        'locale_id' => $language->defaultLocale->id,
                        'language_string_type_id' => $stringType->id,
                    ], [
                        'text' => $entity->properties[$key],
                    ]);

                    $properties = $entity->properties->forget($key);
                    $entity->properties = $properties;

                    $entity->saveQuietly();
                });
        });
    }

    /** @return MorphMany<LanguageString, $this> */
    public function languageStrings(): MorphMany
    {
        return $this->morphMany(LanguageString::class, 'linked_entry');
    }

    /** @return MorphOne<LanguageString, $this> */
    public function defaultLabel(): MorphOne
    {
        return $this->morphOne(LanguageString::class, 'linked_entry')
            ->whereHas('language', fn ($query) => $query->where('languages.iso_alpha2', 'en'))
            ->whereHas('languageStringType', fn ($query) => $query->where('language_string_types.name', 'label'));
    }

    /** @return MorphOne<LanguageString, $this> */
    public function defaultHint(): MorphOne
    {
        return $this->morphOne(LanguageString::class, 'linked_entry')
            ->whereHas('language', fn ($query) => $query->where('languages.iso_alpha2', 'en'))
            ->whereHas('languageStringType', fn ($query) => $query->where('language_string_types.name', 'hint'));
    }

    public function getLanguageString(string $type, Locale $locale): ?string
    {
        return $this->languageStrings()
            ->whereHas('languageStringType', fn ($query) => $query->where('language_string_types.name', $type))
            ->whereHas('locale', fn (Builder $query) => $query->where('locales.id', $locale->id))
            ->first()?->text;
    }
}
