<?php

namespace Stats4sd\FilamentOdkLink\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stats4sd\FilamentOdkLink\Services\OdkLinkServices\OdkDatasetService;
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
    use OdkDatasetService;
    use OdkFormMediaService;
    use OdkFormService;
    use OdkProjectService;
    use OdkSubmissionService;
    use OdkUserService;

    private string $tokenCacheKey = 'odk-token';

    public function __construct(protected string $endpoint) {}

    /**
     * Creates a new session + auth token for communication with the ODK Central server
     *
     * @return string $token
     */
    public function authenticate(bool $forceRefresh = false): string
    {
        if ($forceRefresh) {
            $this->forgetToken();
        }

        // if a token exists in the cache, return it. Otherwise, create a new session and store the token.
        return Cache::remember($this->tokenCacheKey, now()->addHours(20), function () {

            Log::info('Creating a new ODK Central session', [
                'endpoint' => $this->endpoint,
                'username' => config('filament-odk-link.odk.username'),
            ]);

            $response = Http::post("{$this->endpoint}/sessions", [
                'email' => config('filament-odk-link.odk.username'),
                'password' => config('filament-odk-link.odk.password'),
            ])
                ->throw()
                ->json();

            return $response['token'];
        });
    }

    /**
     * Drops the cached session token so the next authenticate() call starts a fresh
     * ODK Central session. Needed because the token is cached for 20 hours, but ODK
     * Central can invalidate a session at any point (server restart, session purge,
     * password change) — leaving every request 401ing until the cache expires.
     */
    public function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey);
    }

    public function authenticateAsUser($data): Response
    {
        return Http::post("{$this->endpoint}/sessions", $data)
            ->throw();
    }
}
