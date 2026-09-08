<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithOdkCentralAccount;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;

beforeEach(function () {
    Cache::flush();
});

// ─── createUser ───────────────────────────────────────────────────────────────

it('createUser POSTs email and password to /users', function () {
    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/users' => Http::response(['id' => 77, 'email' => 'new@odk.test'], 200),
    ]);

    $result = app(OdkLinkService::class)->createUser('new@odk.test', 'pass123');

    expect($result['id'])->toBe(77);
    Http::assertSent(fn ($req) => $req->url() === 'https://odk.test/v1/users'
        && $req['email'] === 'new@odk.test'
        && $req['password'] === 'pass123'
    );
});

it('createUser falls back to GET /users?q= when ODK Central returns 409', function () {
    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/users' => Http::response(['message' => 'conflict'], 409),
        'https://odk.test/v1/users?q=*' => Http::response([['id' => 55, 'email' => 'existing@odk.test']], 200),
    ]);

    $result = app(OdkLinkService::class)->createUser('existing@odk.test', 'pass123');

    expect($result['id'])->toBe(55);
    Http::assertSent(fn ($req) => str_contains($req->url(), '/users?q='));
});

// ─── assignRole ───────────────────────────────────────────────────────────────

it('assignRole POSTs to /assignments/{role}/{odk_id}', function () {
    $user = new class implements WithOdkCentralAccount
    {
        public int $odk_id = 42;
    };

    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/assignments/manager/42' => Http::response(['success' => true], 200),
    ]);

    $result = app(OdkLinkService::class)->assignRole($user, 'manager');

    expect($result)->toHaveKey('success');
    Http::assertSent(fn ($req) => $req->url() === 'https://odk.test/v1/assignments/manager/42');
});

it('assignRole returns success without re-throwing when ODK Central returns 409', function () {
    $user = new class implements WithOdkCentralAccount
    {
        public int $odk_id = 42;
    };

    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/assignments/manager/42' => Http::response([], 409),
    ]);

    $result = app(OdkLinkService::class)->assignRole($user, 'manager');

    expect($result)->toBe(['success' => true]);
});

// ─── addUserToProject ─────────────────────────────────────────────────────────

it('addUserToProject POSTs to the manager assignment URL when user has odk_id', function () {
    $team = Team::factory()->create();
    $odkProject = OdkProject::create([
        'id' => 5,
        'name' => 'Project',
        'owner_type' => Team::class,
        'owner_id' => $team->id,
    ]);
    $user = new class implements WithOdkCentralAccount
    {
        public int $odk_id = 88;
    };

    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/5/assignments/manager/88' => Http::response([], 200),
    ]);

    app(OdkLinkService::class)->addUserToProject($user, $odkProject);

    Http::assertSent(fn ($req) => str_contains($req->url(), '/projects/5/assignments/manager/88')
    );
});

it('addUserToProject skips the HTTP call and returns success when user has no odk_id', function () {
    $team = Team::factory()->create();
    $odkProject = OdkProject::create([
        'id' => 6,
        'name' => 'Project B',
        'owner_type' => Team::class,
        'owner_id' => $team->id,
    ]);
    $user = new class implements WithOdkCentralAccount
    {
        public ?int $odk_id = null;
    };

    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
    ]);

    $result = app(OdkLinkService::class)->addUserToProject($user, $odkProject);

    expect($result)->toMatchArray(['success' => true]);
    Http::assertNotSent(fn ($req) => str_contains($req->url(), 'assignments'));
});

// ─── removeUserFromProject ────────────────────────────────────────────────────

it('removeUserFromProject DELETEs the manager assignment', function () {
    $team = Team::factory()->create();
    $odkProject = OdkProject::create([
        'id' => 7,
        'name' => 'Project C',
        'owner_type' => Team::class,
        'owner_id' => $team->id,
    ]);
    $user = new class implements WithOdkCentralAccount
    {
        public int $odk_id = 33;
    };

    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/7/assignments/manager/33' => Http::response([], 200),
    ]);

    app(OdkLinkService::class)->removeUserFromProject($user, $odkProject);

    Http::assertSent(fn ($req) => str_contains($req->url(), '/projects/7/assignments/manager/33')
        && $req->method() === 'DELETE'
    );
});
