<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Traits;

use Illuminate\Http\Client\RequestException;
use JsonException;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

trait HasXlsFormDrafts
{
    /**
     * @throws RequestException
     */
    public function deployDraft(OdkLinkService $service): void
    {

        $odkXlsFormDetails = $service->createDraftForm($this);

        $this->updateQuietly([
            'odk_id' => $odkXlsFormDetails['xmlFormId'],
            'odk_draft_token' => $odkXlsFormDetails['draftToken'],
            'odk_version_id' => $odkXlsFormDetails['version'],
            'has_draft' => true,
            'enketo_draft_id' => $odkXlsFormDetails['enketoId'],
        ]);
    }

    /**
     * Method to retrieve the encoded settings for the current draft version on ODK Central
     *
     * @throws JsonException
     */
    public function getDraftQrCodeStringAttribute(): ?string
    {
        if (! $this->has_draft) {
            return null;
        }

        $settings = [
            'general' => [
                'server_url' => config('odk-link.odk.base_endpoint') . "/test/{$this->odk_draft_token}/projects/{$this->owner->odkProject->id}/forms/{$this->odk_id}/draft",
                'form_update_mode' => 'match_exactly',
            ],
            'project' => ['name' => '(DRAFT) ' . $this->title, 'icon' => '📝'],
            'admin' => ['automatic_update' => true],
        ];

        $json = json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return base64_encode(zlib_encode($json, ZLIB_ENCODING_DEFLATE));

    }

    public function updateDraftFormDetails(OdkLinkService $odkLinkService): void
    {
        $updated = $odkLinkService->getDraftFormDetails($this);

        $this->update([
            'odk_draft_token' => $updated['draftToken'],
            'enketo_draft_id' => $updated['enketoId'],
        ]);
    }
}
