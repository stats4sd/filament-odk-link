<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

// Each test owns its own Http::fake() to prevent stub-merging issues.
// Only the cache is cleared globally so no test leaks a token.
beforeEach(function () {
    Cache::flush();
});

// ─── createProject: name logic ────────────────────────────────────────────────

it('createProject prepends the app name prefix to the project name', function () {
    config()->set('app.name', 'TestApp');
    config()->set('app.short_name', null);

    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects' => Http::response(['id' => 1, 'name' => 'TestApp- My Survey'], 200),
    ]);

    app(OdkLinkService::class)->createProject('My Survey');

    Http::assertSent(fn ($req) =>
        $req->url() === 'https://odk.test/v1/projects'
        && $req['name'] === 'TestApp- My Survey'
    );
});

it('createProject uses short_name over app name when configured', function () {
    config()->set('app.name', 'LongAppName');
    config()->set('app.short_name', 'SHORT');

    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects' => Http::response(['id' => 1, 'name' => 'SHORT- Survey'], 200),
    ]);

    app(OdkLinkService::class)->createProject('Survey');

    Http::assertSent(fn ($req) =>
        $req->url() === 'https://odk.test/v1/projects'
        && $req['name'] === 'SHORT- Survey'
    );
});

it('createProject squishes whitespace when the prefixed name exceeds 57 characters', function () {
    config()->set('app.short_name', null);
    config()->set('app.name', 'App');

    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects' => Http::response(['id' => 1, 'name' => 'x'], 200),
    ]);

    // 27 single-char words separated by double spaces — raw length 79, squished length 53.
    // 'App- ' (5 chars) + 79 = 84 raw > 57, but 5 + 53 = 58 > 57 still. Hmm.
    // Need: after-prefix squished total ≤ 57. Use prefix 'App- ' (5) + squished ≤ 52.
    // 26 'A' words with double spaces: 26 + 25*2 = 76 raw, 26+25=51 squished → 5+51=56 ≤ 57.
    $rawName = implode('  ', array_fill(0, 26, 'A')); // 76 raw, 51 squished

    app(OdkLinkService::class)->createProject($rawName);

    Http::assertSent(function ($req) {
        if ($req->url() !== 'https://odk.test/v1/projects') {
            return false;
        }
        $name = $req['name'];

        return ! str_contains($name, '  ') // no double spaces (squished)
            && strlen($name) <= 57;
    });
});

it('createProject limits the name to 57 characters when squish alone is insufficient', function () {
    config()->set('app.short_name', null);
    config()->set('app.name', 'App');

    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects' => Http::response(['id' => 1, 'name' => 'x'], 200),
    ]);

    // 'App- ' (5) + 60 chars with no whitespace = 65 > 57, squish has no effect.
    $longName = str_repeat('X', 60);

    app(OdkLinkService::class)->createProject($longName);

    Http::assertSent(fn ($req) =>
        $req->url() === 'https://odk.test/v1/projects'
        && strlen($req['name']) === 57
    );
});

// ─── createProjectAppUser ─────────────────────────────────────────────────────

it('createProjectAppUser POSTs to app-users then to the manager assignment', function () {
    $team = \Stats4sd\FilamentOdkLink\Tests\Models\Team::factory()->create();
    $odkProject = OdkProject::create([
        'id' => 99,
        'name' => 'Test Project',
        'owner_type' => get_class($team),
        'owner_id' => $team->id,
    ]);

    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/99/app-users' => Http::response([
            'id' => 42,
            'displayName' => 'All Test Project 1',
            'token' => 'app-user-token',
        ], 200),
        'https://odk.test/v1/projects/99/assignments/manager/42' => Http::response([], 200),
    ]);

    $result = app(OdkLinkService::class)->createProjectAppUser($odkProject);

    expect($result['id'])->toBe(42);
    Http::assertSent(fn ($req) => str_contains($req->url(), '/projects/99/app-users'));
    Http::assertSent(fn ($req) => str_contains($req->url(), '/projects/99/assignments/manager/42'));
});

// ─── updateProject ────────────────────────────────────────────────────────────

it('updateProject POSTs to the correct URL with the new name', function () {
    $team = \Stats4sd\FilamentOdkLink\Tests\Models\Team::factory()->create();
    $odkProject = OdkProject::create([
        'id' => 7,
        'name' => 'Old Name',
        'owner_type' => get_class($team),
        'owner_id' => $team->id,
    ]);

    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/7' => Http::response(['id' => 7, 'name' => 'New Name'], 200),
    ]);

    app(OdkLinkService::class)->updateProject($odkProject, 'New Name');

    Http::assertSent(fn ($req) =>
        $req->url() === 'https://odk.test/v1/projects/7'
        && $req['name'] === 'New Name'
    );
});

// ─── archiveProject ───────────────────────────────────────────────────────────

it('archiveProject POSTs archived=true to the project URL', function () {
    $team = \Stats4sd\FilamentOdkLink\Tests\Models\Team::factory()->create();
    $odkProject = OdkProject::create([
        'id' => 5,
        'name' => 'To Archive',
        'owner_type' => get_class($team),
        'owner_id' => $team->id,
    ]);

    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/5' => Http::response(['id' => 5, 'name' => 'To Archive', 'archived' => true], 200),
    ]);

    app(OdkLinkService::class)->archiveProject($odkProject);

    Http::assertSent(fn ($req) =>
        $req->url() === 'https://odk.test/v1/projects/5'
        && $req['archived'] === true
        && $req['name'] === 'To Archive'
    );
});
