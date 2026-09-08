<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
});

it('authenticate POSTs credentials to /sessions and returns the token', function () {
    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'abc123'], 200),
    ]);

    $token = app(OdkLinkService::class)->authenticate();

    expect($token)->toBe('abc123');
    Http::assertSent(fn ($req) =>
        $req->url() === 'https://odk.test/v1/sessions'
        && $req['email'] === 'platform@odk.test'
        && $req['password'] === 'secret'
    );
});

it('authenticate caches the token under odk-token for 20 hours', function () {
    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'cached-token'], 200),
    ]);

    app(OdkLinkService::class)->authenticate();

    expect(Cache::get('odk-token'))->toBe('cached-token');
});

it('authenticate makes no HTTP request on a second call when the token is already cached', function () {
    Http::fake([
        'https://odk.test/v1/sessions' => Http::sequence()
            ->push(['token' => 'first'], 200)
            ->push(['token' => 'second'], 200),
    ]);

    $service = app(OdkLinkService::class);
    $first = $service->authenticate();
    $second = $service->authenticate();

    expect($first)->toBe('first')
        ->and($second)->toBe('first'); // still the cached value
    Http::assertSentCount(1);
});
