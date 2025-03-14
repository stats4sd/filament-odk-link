<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stats4sd\FilamentOdkLink\Models\OdkLink\RequiredMedia;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @phpstan-require-extends Model
 *
 * @property WithXlsforms $owner
 * @property Collection $schema
 * @property string $xlsfile
 * @property string $xlsfile_name
 * @property string $enketo_draft_url
 */
interface WithXlsformDrafts
{
    public function owner(): BelongsTo;

    public function deployDraft(bool $withMedia = true): PendingDispatch;

    public function deployDraftSync(bool $withMedia = true): void;

    public function updateDraftDetails(OdkLinkService $odkLinkService): void;

    /**
     * @throws RequestException
     */
    public function deleteFromOdkCentral(OdkLinkService $odkLinkService): void;

    /******************** COMPUTED ATTRIBUTES ******************/

    public function draftQrCodeString(): Attribute;

    public function requiredMedia(): HasMany;

    public function attachedFixedMedia(): HasMany;

    public function attachedDataMedia(): HasMany;
}
