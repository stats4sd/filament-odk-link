<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource\Pages;

use Filament\Actions;
use Filament\Forms\Get;
use Filament\Resources\Pages\ViewRecord;
use Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Jobs\UpdateXlsformTitleInFile;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class ViewXlsformTemplate extends ViewRecord
{
    protected static string $resource = XlsformTemplateResource::class;

    public function getTitle(): string
    {
        return self::getRecord()->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('update_xlsform_template')
                ->label('Update XLSForm Template')
                ->icon('heroicon-o-pencil')
                ->form(XlsformTemplateResource::getCreateFields())
                ->fillForm(fn() => [
                    'title' => self::getRecord()->title,
                ])
                ->action(function (array $data, XlsformTemplate $record, Get $get) {
                    $this->processRecord($record);
                }),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function processRecord(XlsformTemplate $record): XlsformTemplate
    {
        $odkLinkService = app()->make(OdkLinkService::class);

        $record->owner()->associate(Platform::first());
        $record->saveQuietly();

        // update form title in xlsfile to match user-given title
        UpdateXlsformTitleInFile::dispatchSync($record);

        $record->refresh();
        $record->deployDraft($odkLinkService);
        $record->getRequiredMedia($odkLinkService);

        // TODO: We need to do the extract section when create and edit
        $record->extractSections();

        return $record;
    }
}
