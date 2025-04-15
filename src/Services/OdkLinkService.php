<?php

namespace Stats4sd\FilamentOdkLink\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithOdkCentralAccount;
use Stats4sd\FilamentOdkLink\Services\OdkLinkServices\OdkFormMediaService;
use Stats4sd\FilamentOdkLink\Services\OdkLinkServices\OdkFormService;
use Stats4sd\FilamentOdkLink\Services\OdkLinkServices\OdkProjectService;
use Stats4sd\FilamentOdkLink\Services\OdkLinkServices\OdkSubmissionService;
use Stats4sd\FilamentOdkLink\Services\OdkLinkServices\OdkUserService;

/**
 * All ODK Aggregation services should be able to handle ODK forms, so this interface should always be used.
 */
class OdkLinkService
{
    use OdkProjectService;
    use OdkUserService;
    use OdkFormMediaService;
    use OdkFormService;
    use OdkSubmissionService;

    public function __construct(protected string $endpoint) {}

    /**
     * Creates a new session + auth token for communication with the ODK Central server
     *
     * @return string $token
     */
    public function authenticate(): string
    {
        // if a token exists in the cache, return it. Otherwise, create a new session and store the token.
        return Cache::remember('odk-token', now()->addHours(20), function () {

            $response = Http::post("{$this->endpoint}/sessions", [
                'email' => config('filament-odk-link.odk.username'),
                'password' => config('filament-odk-link.odk.password'),
            ])
                ->throw()
                ->json();

            return $response['token'];
        });
    }

    public function authenticateAsUser($data): \Illuminate\Http\Client\Response
    {
        return Http::post("{$this->endpoint}/sessions", $data)
            ->throw();
    }

}
