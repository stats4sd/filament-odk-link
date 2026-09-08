<?php

use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\ChoiceListResource;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\DatasetResource;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModules\XlsformModuleResource;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModuleVersions\XlsformModuleVersionResource;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Schemas\XlsformTemplateInfoList;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\XlsformTemplateResource;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('serves the admin login page', function () {
    get('/admin/login')->assertOk();
});

it('redirects guests from the admin dashboard to login', function () {
    get('/admin')->assertRedirect('/admin/login');
});

it('renders the admin dashboard for a seeded admin', function () {
    actingAs(seededAdmin());

    get('/admin')->assertOk();
});

it('renders every package resource index page', function (string $resource) {
    actingAs(seededAdmin());

    get($resource::getUrl('index'))->assertOk();
})->with([
    'templates' => XlsformTemplateResource::class,
    'modules' => XlsformModuleResource::class,
    'module versions' => XlsformModuleVersionResource::class,
    'datasets' => DatasetResource::class,
    'choice lists' => ChoiceListResource::class,
]);

it('renders the template create page against the seeded Platform', function () {
    actingAs(seededAdmin());

    get(XlsformTemplateResource::getUrl('create'))->assertOk();
});

// The template view and edit pages assume an imported xlsx: the infolist reads
// `$record->rootSection->schema` (XlsformTemplateInfoList.php:112) and the page load calls ODK Central through
// XlsformTemplate::getRequiredMedia(). Rendering them needs the import pipeline run against a fixture file,
// which is the UI-test-host work of restructure steps 5 and 6. Until then, prove the schema classes at least
// resolve (the import of XlsformTemplateInfoList was case-mismatched and fataled on Linux).
it('resolves the template resource schema classes', function () {
    expect(class_exists(XlsformTemplateInfoList::class))->toBeTrue();
    expect((new ReflectionMethod(XlsformTemplateResource::class, 'infolist'))->isStatic())->toBeTrue();
});

it('renders the template view and edit pages for an imported template')
    ->todo('needs the import pipeline run against a fixture xlsx and a faked ODK Central; see RESTRUCTURING.md step 5/6');
