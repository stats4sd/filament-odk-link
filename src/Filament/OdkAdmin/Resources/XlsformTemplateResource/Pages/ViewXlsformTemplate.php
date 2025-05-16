<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource\Pages;

use App\Services\FilamentHelperService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Get;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class ViewXlsformTemplate extends ViewRecord
{
    protected static string $resource = XlsformTemplateResource::class;

    /**
     * @phpstan-return XlsformTemplate
     */
    public function getRecord(): Model|XlsformTemplate
    {
        /** @var XlsformTemplate $record */
        $record = parent::getRecord();

        return $record;
    }

    public function getTitle(): string
    {
        return self::getRecord()->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('update_test')
                ->label('Update Test on ODK Central')
                ->icon('heroicon-o-pencil')
                ->action(function (array $data, XlsformTemplate $record, Get $get) {
                    $this->updateTest($record);
                }),
            Action::make('view_on_o_d_k_central')
                ->label('View on ODK Central')
                ->icon('heroicon-o-document-text')
                ->url(fn (XlsformTemplate $record) => config('filament-odk-link.odk.url').'/#/projects/'.FilamentHelperService::getTenant()->odkProject->id.'/forms/'.$record->odk_id.'/draft/'),
            Actions\Action::make('update_xlsform_template')
                ->label('Replace XLSForm')
                ->icon('heroicon-o-document-arrow-up')
                ->form(XlsformTemplateResource::getCreateFields())
                ->fillForm(fn () => [
                    'title' => self::getRecord()->title,
                ])
                ->action(function (array $data, XlsformTemplate $record, Get $get) {
                    XlsformTemplateResource::processRecord($record);
                }),
            Actions\EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->label('Edit Media & Data'),
            Actions\DeleteAction::make(),
        ];
    }

    protected function updateTest(XlsformTemplate $record): XlsformTemplate
    {
        // for each linked dataset...
        $odkLinkService = app()->make(OdkLinkService::class);

        $record->deployDraft($odkLinkService);

        $record->save();

        return $record;
    }
}
