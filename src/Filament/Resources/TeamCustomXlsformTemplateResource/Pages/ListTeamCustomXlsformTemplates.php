<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\CustomXlsformTemplateResource\Pages;

use App\Filament\App\Clusters\XlsformsCluster\Resources\CustomXlsformTemplateResource;
use App\Filament\App\Clusters\XlsformsCluster\Widgets\CustomOdkTemplatesWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTeamCustomXlsformTemplates extends ListRecords
{
    protected static string $resource = CustomXlsformTemplateResource::class;

    protected ?string $heading = 'Custom Xlsform Templates';

    protected function getHeaderWidgets(): array
    {
        return [
            CustomOdkTemplatesWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

}
