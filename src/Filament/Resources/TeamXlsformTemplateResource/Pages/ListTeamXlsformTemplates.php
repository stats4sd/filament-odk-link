<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\TeamXlsformTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Filament\Widgets\AvailableOdkTemplatesWidget;

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
