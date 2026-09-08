<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Abstracts;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use JsonException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\DeployDraftXlsformToOdkCentral;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsformDrafts;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @property bool $has_draft
 * @property string $title
 * @property ?string $odk_id
 * @property ?string $odk_draft_token
 * @property ?string $enketo_draft_id
 * @property ?Carbon $odk_draft_updated_at
 * @property Media|UploadedFile|null $xlsfile
 */
abstract class HasXlsformDrafts extends Model implements HasMedia, WithXlsformDrafts
{
    use InteractsWithMedia;

    /** @return BelongsTo<Model, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('filament-odk-link.models.form_owner'), 'owner_id');
    }

    /**************** METHODS *************************/

    public function deployDraft(bool $withMedia = true): ?PendingDispatch
    {
        return DeployDraftXlsformToOdkCentral::dispatch($this, $withMedia, auth()->user());
    }

    public function deployDraftSync(bool $withMedia = true): void
    {
        DeployDraftXlsformToOdkCentral::dispatchSync($this, $withMedia, auth()->user());
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function updateDraftDetails(OdkLinkService $odkLinkService): void
    {
        $odkXlsFormDetails = $odkLinkService->getXlsformDraftDetails($this);

        $this->update([
            'odk_id' => $odkXlsFormDetails['xmlFormId'],
            'odk_draft_token' => $odkXlsFormDetails['draftToken'],
            'odk_version_id' => $odkXlsFormDetails['version'],
            'has_draft' => true,
            'enketo_draft_id' => $odkXlsFormDetails['enketoId'],
            'odk_draft_updated_at' => new Carbon($odkXlsFormDetails['updatedAt']),
            'draft_needs_update' => false,
        ]);
    }

    /**
     * @throws RequestException
     */
    public function deleteFromOdkCentral(OdkLinkService $odkLinkService): void
    {
        $odkLinkService->deleteForm($this);
    }

    /******************** COMPUTED ATTRIBUTES ******************/

    /**
     * Method to retrieve the encoded settings for the current draft version on ODK Central
     *
     * @throws JsonException
     */
    /** @return Attribute<?string, never> */
    public function draftQrCodeString(): Attribute
    {

        return new Attribute(
            get: function () {

                if (! $this->has_draft) {
                    return null;
                }

                $settings = [
                    'general' => [
                        'server_url' => config('filament-odk-link.odk.base_endpoint') . "/test/$this->odk_draft_token/projects/{$this->owner->odkProject->id}/forms/$this->odk_id/draft",
                        'form_update_mode' => 'match_exactly',
                    ],
                    'project' => ['name' => '(DRAFT) ' . $this->title, 'icon' => '📝'],
                    'admin' => ['automatic_update' => true],
                ];

                $json = json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

                return base64_encode(zlib_encode($json, ZLIB_ENCODING_DEFLATE));

            }
        );

    }

    /** @return Attribute<Media, never> */
    protected function xlsfile(): Attribute
    {
        return new Attribute(
            get: fn (): Media => $this->getFirstMedia('xlsform_file'),
        );
    }

    /** @return Attribute<string, never> */
    protected function xlsfileName(): Attribute
    {
        return new Attribute(
            get: fn (): ?string => $this->getFirstMedia('xlsform_file')?->file_name,
        );
    }

    /** @return Attribute<string, never> */
    protected function enketoDraftUrl(): Attribute
    {
        return new Attribute(
            get: function () {
                // if there is no enketo id in the database, retrieve it from ODK Central
                if (! $this->enketo_draft_id || Str::endsWith($this->enketo_draft_id, '/-/')) {
                    $this->updateDraftDetails(app()->make(OdkLinkService::class));
                    $this->refresh();
                }

                return config('filament-odk-link.odk.url') . '/-/' . $this->enketo_draft_id;

            },
        );
    }
}
