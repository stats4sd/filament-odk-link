<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Stats4sd\FilamentOdkLink\Models\ChoiceListEntryRemoved;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

/**
 * @phpstan-require-extends Model
 *
 * @property ?OdkProject $odkProject
 * @property string $name
 * @property Collection<Xlsform> $xlsforms
 * @property Collection<Locale> $locales
 * @property Collection<ChoiceListEntryRemoved> $choiceListEntriesRemoved
 */
interface WithXlsforms
{
    public function datasets(): MorphMany;

    public function xlsforms(): MorphMany;

    public function locales(): HasManyThrough;

    public function languages(): HasManyThrough;

    // Private templates are owned by a single form owner.
    // All owners have access to all public templates (templates where available = 1)
    public function xlsformTemplates(): MorphMany;

    public function odkProject(): MorphOne;

    public function createLinkedOdkProject(OdkLinkService $odkLinkService): void;

    public function choiceListEntriesRemoved(): MorphMany;

}
