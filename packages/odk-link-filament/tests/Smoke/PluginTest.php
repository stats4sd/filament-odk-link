<?php

use Filament\Panel;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\ChoiceListResource;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\DatasetResource;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModules\XlsformModuleResource;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModuleVersions\XlsformModuleVersionResource;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\XlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Filament\Widgets\AvailableOdkTemplatesWidget;
use Stats4sd\FilamentOdkLink\Filament\Widgets\OdkUrlAlertWidget;
use Stats4sd\FilamentOdkLink\OdkLinkAdmin;

it('discovers the admin resources and widgets into the panel it is registered on', function () {
    $panel = Panel::make()->id('admin')->plugin(OdkLinkAdmin::make());

    expect(array_values($panel->getResources()))->toEqualCanonicalizing([
        ChoiceListResource::class,
        DatasetResource::class,
        XlsformModuleResource::class,
        XlsformModuleVersionResource::class,
        XlsformTemplateResource::class,
    ])
        ->and(array_values($panel->getWidgets()))->toEqualCanonicalizing([
            AvailableOdkTemplatesWidget::class,
            OdkUrlAlertWidget::class,
        ])
        ->and($panel->getPlugin('stats4sd-odk-link'))->toBeInstanceOf(OdkLinkAdmin::class);
});
