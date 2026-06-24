<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Traits;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

trait HasXlsformTemplates
{
    /** @throws BindingResolutionException */
    protected static function bootHasXlsformTemplates(): void
    {
        // check if we are in local-only (no-ODK link) mode
        if (config('filament-odk-link.odk.url') === null || config('filament-odk-link.odk.url') == '') {
            return;
        }

        $odkLinkService = app()->make(OdkLinkService::class);

        // when the model is created; automatically create an associated project on ODK Central;
        static::created(static function (self $owner) use ($odkLinkService) {

            // check if we are in local-only (no-ODK link) mode
            if (! config('filament-odk-link.odk.url')) {
                return;
            }

            $owner->createLinkedOdkProject($odkLinkService);
        });
    }

    // Private templates are owned by a single form owner.
    // All owners have access to all public templates (templates where available = 1)
    // This is still a morph relationship because XlsformTemplates might be owned by multiple types of entity.
    /** @return MorphMany<XlsformTemplate, $this> */
    public function xlsformTemplates(): MorphMany
    {
        return $this->morphMany(XlsformTemplate::class, 'owner');
    }

    // ODK projects might be owned by 'xlsform owners', or the platform itself.
    /** @return MorphOne<OdkProject, $this> */
    public function odkProject(): MorphOne
    {
        return $this->morphOne(OdkProject::class, 'owner');
    }

    public function createLinkedOdkProject(OdkLinkService $odkLinkService): void
    {
        $odkProjectInfo = $odkLinkService->createProject($this->name);
        $odkProject = $this->odkProject()->create([
            'id' => $odkProjectInfo['id'],
            'name' => $odkProjectInfo['name'],
            'archived' => $odkProjectInfo['archived'],
        ]);

        // create an app user + assign to all forms in the project by giving them the admin role;
        $odkAppUserInfo = $odkLinkService->createProjectAppUser($odkProject);

        $odkProject->appUsers()->create([
            'id' => $odkAppUserInfo['id'],
            'display_name' => $odkAppUserInfo['displayName'],
            'type' => 'field_key', // legacy term for "App User" in ODK Central;
            'token' => $odkAppUserInfo['token'], // the token required to generate the ODK QR Code;
            'can_access_all_forms' => true,
        ]);
    }

    /** @return Attribute<string, never> */
    protected function odkQrCode(): Attribute
    {
        return new Attribute(
            get: function (): ?string {

                if (! $this->odkProject || ! $this->odkProject->appUsers->first()) {
                    return null;
                }

                return $this->odkProject->appUsers->first()->qr_code_string;
            }
        );
    }
}
