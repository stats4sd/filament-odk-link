<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use JsonException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

/**
 * @phpstan-require-extends Model
 *
 * @property WithXlsforms $owner
 */
interface WithXlsformDrafts
{
    /** @return BelongsTo */
    public function owner(): BelongsTo;

    public function deployDraft(OdkLinkService $service, bool $withMedia = true): bool;

    public function updateDraftFormDetails(OdkLinkService $odkLinkService): void;

}
