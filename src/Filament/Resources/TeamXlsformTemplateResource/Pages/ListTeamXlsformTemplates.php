<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\CustomXlsformTemplateResource\Pages;

use App\Filament\App\Clusters\XlsformsCluster\Resources\XlsformTemplateResource;
use App\Filament\App\Clusters\XlsformsCluster\Widgets\AvailableOdkTemplatesWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTeamXlsformTemplates extends ListRecords
{
    protected static string $resource = XlsformTemplateResource::class;

    protected ?string $heading = 'Available Xlsform Templates';

    protected function getHeaderWidgets(): array
    {
        return [
            AvailableOdkTemplatesWidget::class,
        ];
    }

}