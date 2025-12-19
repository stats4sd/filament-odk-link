<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplateResources\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Stats4sd\FilamentOdkLink\Filament\Widgets\AvailableOdkTemplatesWidget;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplates\TeamXlsformTemplateResource;

class ListTeamXlsformTemplates extends ListRecords
{
    protected static string $resource = TeamXlsformTemplateResource::class;

    protected ?string $heading = 'Available Xlsform Templates';

    protected function getHeaderWidgets(): array
    {
        return [
            AvailableOdkTemplatesWidget::class,
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

}
