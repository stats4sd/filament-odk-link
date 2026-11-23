<?php

use Filament\Support\Facades\FilamentAsset;
use Stats4sd\FilamentOdkLink\Contracts\CurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Contracts\OperationNotifier;
use Stats4sd\FilamentOdkLink\Filament\Support\FilamentCurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Filament\Support\FilamentOperationNotifier;
use Stats4sd\FilamentOdkLink\OdkLinkCoreServiceProvider;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

it('registers core through the UI provider and binds the Filament adapters', function () {
    expect(app()->getProviders(OdkLinkCoreServiceProvider::class))->toHaveCount(1)
        ->and(app(OdkLinkService::class))->toBeInstanceOf(OdkLinkService::class)
        ->and(app(CurrentOwnerResolver::class))->toBeInstanceOf(FilamentCurrentOwnerResolver::class)
        ->and(app(OperationNotifier::class))->toBeInstanceOf(FilamentOperationNotifier::class);
});

it('registers the package views under the filament-odk-link namespace', function (string $view) {
    expect(view()->exists("filament-odk-link::{$view}"))->toBeTrue();
})->with([
    'filament.widgets.odk-url-alert-widget',
    'filament.widgets.available-templates-widget',
    'filament.forms.components.html-block',
    'filament.tables.columns.required-data-media-count',
]);

it('registers the package stylesheet and no script asset', function () {
    $styles = FilamentAsset::getStyles(['stats4sd/filament-odk-link']);

    expect($styles)->toHaveCount(1)
        ->and($styles[0]->getId())->toBe('filament-odk-link-styles')
        ->and(file_exists($styles[0]->getPath()))->toBeTrue()
        ->and(FilamentAsset::getScripts(['stats4sd/filament-odk-link']))->toBeEmpty();
});
