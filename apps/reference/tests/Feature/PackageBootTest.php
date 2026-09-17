<?php

use App\Models\Team;
use Illuminate\Support\Facades\Schema;
use Stats4sd\FilamentOdkLink\FilamentOdkLinkServiceProvider;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

it('registers the package service provider', function () {
    expect(app()->getProviders(FilamentOdkLinkServiceProvider::class))->not->toBeEmpty();
    expect(app(OdkLinkService::class))->toBeInstanceOf(OdkLinkService::class);
});

it('creates the package, permission and host tables from the published migrations', function () {
    foreach (['teams', 'team_user', 'users', 'notifications', 'roles', 'media', 'xlsform_templates', 'xlsforms', 'platforms', 'submissions'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing table {$table}");
    }
});

it('resolves the form owner to the host Team model', function () {
    expect(config('filament-odk-link.models.form_owner'))->toBe(Team::class);
    expect(config('filament-odk-link.odk.url'))->toBeEmpty();
});
