<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Abstracts;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use JsonException;
use Spatie\MediaLibrary\InteractsWithMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsformDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\PublishesToOdkCentral;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Throwable;

/**
 * @property bool $has_draft
 * @property string $title
 * @property ?string $odk_id
 * @property ?string $odk_draft_token
 * @property ?string $enketo_draft_id
 */
abstract class HasXlsformDrafts extends Model implements WithXlsformDrafts
{
    use InteractsWithMedia;
    use PublishesToOdkCentral;


    /** @return MorphTo */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function sendDraftToOdkCentral(OdkLinkService $service, bool $withMedia = true): bool
    {
        try {
            $odkXlsFormDetails = $service->createDraftForm($this, $withMedia);

        } catch (Throwable $e) {

            Notification::make('draft-form-failed')
                ->title('There is an error in the XLS Form')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return false;
        }

        $this->updateQuietly([
            'odk_id' => $odkXlsFormDetails['xmlFormId'],
            'odk_draft_token' => $odkXlsFormDetails['draftToken'],
            'odk_version_id' => $odkXlsFormDetails['version'],
            'has_draft' => true,
            'enketo_draft_id' => $odkXlsFormDetails['enketoId'],
        ]);

        return true;
    }

    /**
     * Method to retrieve the encoded settings for the current draft version on ODK Central
     *
     * @throws JsonException
     */
    /** @return Attribute<?string, never> */
    protected function draftQrCodeString(): Attribute
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

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function updateDraftFormDetails(OdkLinkService $odkLinkService): void
    {
        $updated = $odkLinkService->getDraftFormDetails($this);

        $this->update([
            'odk_draft_token' => $updated['draftToken'],
            'enketo_draft_id' => $updated['enketoId'],
        ]);
    }
}
