<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Schemas\XlsformTemplateForm;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Schemas\XlsformTemplateInfoList;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\XlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class EditXlsformTemplate extends EditRecord
{
    use EditRecord\Concerns\HasWizard;

    protected static string $resource = XlsformTemplateResource::class;

    // return empty array, so that there is no relation manager showed in Edit page
    public function getRelationManagers(): array
    {
        return [];
    }

    /**
     * @phpstan-return XlsformTemplate
     */
    public function getRecord(): Model | XlsformTemplate
    {
        /** @var XlsformTemplate $record */
        $record = parent::getRecord();

        return $record;
    }

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
                    XlsformTemplateForm::getCreateFields(),
                ),
            Step::make('2. Add Media Files')
                ->description('Add any static media required by the form')
                ->schema(
                    XlsformTemplateForm::getStaticMediaFields(),
                ),
            Step::make('3. Link Required Datasets')
                ->description('Add / link external datasets for lookup tables')
                ->schema(
                    XlsformTemplateForm::getDatasetMediaFields(),
                ),
            Step::make('4. Review Xlsform Structure')
                ->description('How should the collected data be handled?')
                ->schema(XlsformTemplateInfoList::getXlsformSectionFields()),

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

    protected function afterSave(): void
    {
        // re-extract ODK template sections
        $this->getRecord()->extractSections();

    }
}
