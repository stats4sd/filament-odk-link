<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('serves the team login page', function () {
    get('/team/login')->assertOk();
});

it('sends a user with a team to that tenant dashboard', function () {
    $user = seededAdmin();
    $team = $user->teams()->first();

    actingAs($user);

    get('/team')->assertRedirect("/team/{$team->getKey()}");
    get("/team/{$team->getKey()}")->assertOk();
});

it('returns 404 for a user with no team, because no tenant registration page is configured', function () {
    actingAs(User::factory()->create());

    get('/team')->assertNotFound();
});

it('forbids a user from another user\'s team', function () {
    $owner = seededAdmin();
    $team = $owner->teams()->first();

    actingAs(User::factory()->create());

    get("/team/{$team->getKey()}")->assertNotFound();
});
