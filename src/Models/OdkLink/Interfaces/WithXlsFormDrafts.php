<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

interface WithXlsFormDrafts
{
    // If it can have a draft, it must have an owner
    public function owner(): MorphTo;

    public function xlsfile(): Attribute;

    public function getDraftQrCodeStringAttribute(): ?string;

    public function deployDraft(OdkLinkService $service): void;

    public function getOdkLinkAttribute(): ?string;

    // it must have media file attachments
    public function attachedFixedMedia();

    public function attachedDataMedia();
}
