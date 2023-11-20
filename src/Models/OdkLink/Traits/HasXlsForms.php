<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Traits;

use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasXlsForms
{
    protected static function booted(): void
    {
        parent::booted();

        $odkLinkService = app()->make(OdkLinkService::class);

        // when the model is created; automatically create an associated project on ODK Central;
        static::created(static function ($owner) use ($odkLinkService) {
            $owner->createLinkedOdkProject($odkLinkService, $owner);
        });
    }

    // Used as the human-readable label for the owners of forms. Uses the same variable name that some Laravel Backpack fields expect (e.g. Relationship)
    // Xls Form titles are in the format `$owner->$nameAttribute . '-' . $xlsform->title`
    public string $identifiableAttribute = 'name';

    public function xlsforms(): MorphMany
    {
        return $this->morphMany(Xlsform::class, 'owner');
    }


    // Private templates are owned by a single form owner.
    // All owners have access to all public templates (templates where available = 1)
    public function xlsformTemplates(): MorphMany
    {
        return $this->morphMany(XlsformTemplate::class, 'owner');
    }

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


}
