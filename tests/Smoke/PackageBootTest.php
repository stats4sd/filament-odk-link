<?php

use Stats4sd\FilamentOdkLink\Facades\FilamentOdkLink as FilamentOdkLinkFacade;
use Stats4sd\FilamentOdkLink\FilamentOdkLink;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Stats4sd\FilamentOdkLink\Services\XlsformTranslationHelper;

it('resolves the OdkLinkService as a singleton', function () {
    $a = app(OdkLinkService::class);
    $b = app(OdkLinkService::class);

    expect($a)->toBeInstanceOf(OdkLinkService::class)
        ->and($a)->toBe($b);
});

it('resolves the XlsformTranslationHelper as a singleton', function () {
    expect(app(XlsformTranslationHelper::class))
        ->toBeInstanceOf(XlsformTranslationHelper::class)
        ->toBe(app(XlsformTranslationHelper::class));
});

it('resolves the FilamentOdkLink facade', function () {
    expect(FilamentOdkLinkFacade::getFacadeRoot())->toBeInstanceOf(FilamentOdkLink::class);
});

it('auto-registers every console command in src/Commands', function (string $signature) {
    expect(array_keys(Artisan::all()))->toContain($signature);
})->with([
    'odk:poll-for-odk-data',
    'odk:get-submissions-quietly',
    'odk:test-csv-media-generation',
    'app:update-xlsform-drafts',
    'app:generate-submissions',
    'odk:trs',
]);

it('registers the submission update route', function () {
    expect(Route::has('submission.update'))->toBeTrue();
});
