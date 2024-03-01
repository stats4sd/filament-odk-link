<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

trait PublishesToOdkCentral
{
    public function xlsfile(): Attribute
    {
        return new Attribute(
            get: fn(): string => $this->getFirstMediaPath('xlsform_file'),
        );
    }

    public function xlsfileName(): Attribute
    {
        return new Attribute(
            get: fn(): ?string => $this->getFirstMedia('xlsform_file')?->file_name,
        );
    }

    public function enketoDraftUrl(): Attribute
    {
        return new Attribute(
            get: function () {
                // if there is no enketo id in the database, retrieve it from ODK Central
                if (!$this->enketo_draft_id || Str::endsWith($this->enketo_draft_id, '/-/')) {
                    $this->updateDraftFormDetails(app()->make(OdkLinkService::class));
                }

                return config('filament-odk-link.odk.url') . '/-/' . $this->enketo_draft_id;

            },
        );
    }

    /**
     * @throws RequestException
     */
    public function publishForm(OdkLinkService $odkLinkService): void
    {


        // check if there is a draft. If not, create one.
        if (!$this->has_draft || !$this->has_latest_template) {
            $hasDraft = $this->deployDraft($odkLinkService);
        }

        // if the draft was successfully created; publish it.
        if ($hasDraft) {
            $odkLinkService->publishForm($this);
        }
    }


    public function deleteFromOdkCentral(OdkLinkService $odkLinkService): void
    {
        $odkLinkService->deleteForm($this);
    }
}
