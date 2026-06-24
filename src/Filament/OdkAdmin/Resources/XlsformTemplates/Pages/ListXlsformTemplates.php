<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Pages;

use Filament\Actions;
use Livewire\Attributes\On;
use Filament\Resources\Pages\ListRecords;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;
use Stats4sd\FilamentOdkLink\Filament\Widgets\OdkUrlAlertWidget;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\XlsformTemplateResource;

class ListXlsformTemplates extends ListRecords
{
    protected static string $resource = XlsformTemplateResource::class;

    protected function getHeaderWidgets(): array
    {
        $widgets = [];

        // check config item existence, and check empty config item
        if (config('filament-odk-link.odk.url') === null || config('filament-odk-link.odk.url') == '') {

            $widgets[] = OdkUrlAlertWidget::class;
        }

        return $widgets;
    }

    protected function getHeaderActions(): array
    {
        return [

            Actions\Action::make('view-platform-templates-on-odk-central')
            ->label('View Platform Templates on ODK Central')
            ->url(Platform::first()?->odkProject?->odk_url)
            ->openUrlInNewTab(),
            Actions\CreateAction::make(),
        ];
    }

    #[On('echo.xlsforms,XlsformTemplateWasImported')]
    public function updateTable(): void
    {
        $this->resetTable();
    }
}
