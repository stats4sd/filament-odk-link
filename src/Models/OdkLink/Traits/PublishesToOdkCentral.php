<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

trait PublishesToOdkCentral
{
    public function xlsfile(): Attribute
    {
        return new Attribute(
            get: fn (): string => $this->getFirstMediaPath('xlsform_file'),
        );
    }

    public function xlsfileName(): Attribute
    {
        return new Attribute(
            get: fn (): ?string => $this->getFirstMedia('xlsform_file')?->file_name,
        );
    }

    public function enketoDraftUrl(): Attribute
    {
        return new Attribute(
            get: function () {
                // if there is no enketo id in the database, retrieve it from ODK Central
                if (! $this->enketo_draft_id || Str::endsWith($this->enketo_draft_id, '/-/')) {
                    $this->updateDraftFormDetails(app()->make(OdkLinkService::class));
                }

                return config('odk-link.odk.url') . '/-/' . $this->enketo_draft_id;

            },
        );
    }

    public function deleteFromOdkCentral(OdkLinkService $odkLinkService): void
    {
        $odkLinkService->deleteForm($this);
    }
}
