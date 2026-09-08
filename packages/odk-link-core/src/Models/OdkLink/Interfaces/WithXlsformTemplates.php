<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

/**
 * @phpstan-require-extends Model
 *
 * @property Collection<XlsformTemplate> $xlsformTemplates
 * @property OdkProject $odkProject
 * @property bool $should_receive_all_xlsform_templates
 */
interface WithXlsformTemplates
{
    // Private templates are owned by a single form owner.
    // All owners have access to all public templates (templates where available = 1)
    public function xlsformTemplates(): MorphMany;

    public function odkProject(): MorphOne;

    public function createLinkedOdkProject(OdkLinkService $odkLinkService): void;
}
