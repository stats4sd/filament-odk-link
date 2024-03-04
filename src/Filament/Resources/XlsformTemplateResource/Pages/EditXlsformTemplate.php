<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource\Pages;

use Filament\Actions;
use Filament\Forms\Components\Wizard\Step;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource;

class EditXlsformTemplate extends EditRecord
{
    use EditRecord\Concerns\HasWizard;

    protected static string $resource = XlsformTemplateResource::class;

    public function getTitle(): string
    {
        return 'Edit ' . self::getRecord()->title;
    }

    public function getSteps(): array
    {
        return [
            Step::make('1. Xlsform')
                ->description('Upload your XLSForm file and give it a title')
                ->schema(
                    XlsformTemplateResource::getCreateFields(),
                ),
            Step::make('2. Add Media Files')
                ->description('Add any static media required by the form')
                ->schema(
                    XlsformTemplateResource::getStaticMediaFields(),
                ),
            Step::make('3. Link Required Datasets')
                ->description('Add / link external datasets for lookup tables')
                ->schema(
                    XlsformTemplateResource::getDatasetMediaFields(),
                ),
            Step::make('4. Review Xlsform Structure')
                ->description('How should the collected data be handled?')
                ->schema(XlsformTemplateResource::getXlsformSectionFields()),

        ];
    }

    public function getStartStep(): int
    {
        return 2;
    }

    public function hasSkippableSteps(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }

}
