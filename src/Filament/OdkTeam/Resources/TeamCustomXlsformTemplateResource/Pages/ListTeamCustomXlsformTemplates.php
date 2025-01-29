<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamCustomXlsformTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamCustomXlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Widgets\CustomOdkTemplatesWidget;

class ListTeamCustomXlsformTemplates extends ListRecords
{
    protected static string $resource = TeamCustomXlsformTemplateResource::class;

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
