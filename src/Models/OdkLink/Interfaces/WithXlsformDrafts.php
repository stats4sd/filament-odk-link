<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use JsonException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

interface WithXlsformDrafts
{
    /** @return MorphTo */
    public function owner(): MorphTo;

    public function deployDraft(OdkLinkService $service, bool $withMedia = true): bool;

    /**
     * Method to retrieve the encoded settings for the current draft version on ODK Central
     *
     * @throws JsonException
     */
    public function getDraftQrCodeStringAttribute(): ?string;

    public function updateDraftFormDetails(OdkLinkService $odkLinkService): void;

}
