<?php

use Stats4sd\FilamentOdkLink\Contracts\CurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Contracts\OperationNotifier;
use Stats4sd\FilamentOdkLink\Filament\Support\FilamentCurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Filament\Support\FilamentOperationNotifier;
use Stats4sd\FilamentOdkLink\Filament\Tests\CoreRegisteredFirstTestCase;
use Stats4sd\FilamentOdkLink\OdkLinkCoreServiceProvider;

uses(CoreRegisteredFirstTestCase::class);

it('binds the Filament adapters when core is registered before the UI provider', function () {
    expect(app(CurrentOwnerResolver::class))->toBeInstanceOf(FilamentCurrentOwnerResolver::class)
        ->and(app(OperationNotifier::class))->toBeInstanceOf(FilamentOperationNotifier::class)
        ->and(app()->getProviders(OdkLinkCoreServiceProvider::class))->toHaveCount(1);
});
