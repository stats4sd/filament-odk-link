<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Filament\Widgets\OdkLinkUrlAlert;
use Stats4sd\FilamentOdkLink\Filament\Widgets\OdkUrlAlertWidget;

class ListXlsformTemplates extends ListRecords
{
    protected static string $resource = XlsformTemplateResource::class;

    public function getHeaderWidgets(): array
    {
        $widgets = [];

        if(config('filament-odk-link.odk.url') === null) {

                $widgets[] = OdkUrlAlertWidget::class;
        }

        return $widgets;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
