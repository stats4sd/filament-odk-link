<?php

use Stats4sd\OdkLink\Core\Contracts\CurrentOwnerResolver;
use Stats4sd\OdkLink\Core\Contracts\OperationNotifier;
use Stats4sd\OdkLink\Core\OdkLinkCoreServiceProvider;
use Stats4sd\OdkLink\Filament\Support\FilamentCurrentOwnerResolver;
use Stats4sd\OdkLink\Filament\Support\FilamentOperationNotifier;
use Stats4sd\OdkLink\Filament\Tests\CoreRegisteredFirstTestCase;

uses(CoreRegisteredFirstTestCase::class);

it('binds the Filament adapters when core is registered before the UI provider', function () {
    expect(app(CurrentOwnerResolver::class))->toBeInstanceOf(FilamentCurrentOwnerResolver::class)
        ->and(app(OperationNotifier::class))->toBeInstanceOf(FilamentOperationNotifier::class)
        ->and(app()->getProviders(OdkLinkCoreServiceProvider::class))->toHaveCount(1);
});
