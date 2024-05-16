<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

interface WithXlsforms
{

    public function xlsforms(): MorphMany;

    // Private templates are owned by a single form owner.
    // All owners have access to all public templates (templates where available = 1)
    public function xlsformTemplates(): MorphMany;

    public function odkProject(): MorphOne;

    public function createLinkedOdkProject(OdkLinkService $odkLinkService): void;

    public function odkQrCode(): Attribute;

}
