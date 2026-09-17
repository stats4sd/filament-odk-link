<?php

use Filament\Schemas\Schema;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\ChoiceListResource;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\DatasetResource;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModules\XlsformModuleResource;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModuleVersions\XlsformModuleVersionResource;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Schemas\XlsformTemplateInfoList;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\XlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

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
// `$record->rootSection->schema` (XlsformTemplateInfoList.php:112), which is null on a template that has not been
// through the import pipeline. Rendering them needs a fixture xlsx run through the import jobs, which is the
// UI-test-host work of restructure steps 5 and 6. Until then, build the infolist schema so the resource's
// `use Schemas\XlsformTemplateInfoList` import is actually autoloaded (it was case-mismatched and fataled on Linux).
it('builds the template infolist schema, autoloading the schema class the resource imports', function () {
    actingAs(seededAdmin());

    // Quietly: the saving hook runs the import pipeline, which needs an uploaded xlsx.
    $template = XlsformTemplate::query()->forceCreateQuietly([
        'title' => 'Smoke test template',
        'owner_type' => Platform::class,
        'owner_id' => Platform::query()->first()->id,
        'processing' => false,
    ]);

    $schema = XlsformTemplateResource::infolist(Schema::make()->record($template));

    expect($schema)->toBeInstanceOf(Schema::class);
    // Second argument false: report whether the class is already loaded, without autoloading it here.
    expect(class_exists(XlsformTemplateInfoList::class, false))->toBeTrue();
});

it('renders the template view and edit pages for an imported template')
    ->todo('needs a fixture xlsx run through the import pipeline; see RESTRUCTURING.md step 5/6');
