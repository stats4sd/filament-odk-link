<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource\Pages;

use Filament\Actions;
use Filament\Forms\Get;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class ViewXlsformTemplate extends ViewRecord
{
    protected static string $resource = XlsformTemplateResource::class;

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
        return self::getRecord()->title;
    }

    protected function getHeaderActions(): array    {
        return [
            Actions\Action::make('make_template_available')
                ->label('Make Template Available')
                ->icon('heroicon-o-pencil')
                ->disabled(fn ($record) => $record->available == true)
                ->action(function (array $data, XlsformTemplate $record, Get $get) {
                    $this->makeTemplateAvailable($record);
                }),
            Actions\Action::make('update_xlsform_template')
                ->label('Replace XLSForm')
                ->icon('heroicon-o-document-arrow-up')
                ->form(XlsformTemplateResource::getCreateFields())
                ->fillForm(fn () => [
                    'title' => self::getRecord()->title,
                ])
                ->action(function (array $data, XlsformTemplate $record, Get $get) {
                    $record->update([
                        'title' => $data['title'],
                    ]);
                }),
            Actions\EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->label('Edit Media & Data'),
            Actions\DeleteAction::make(),
        ];
    }

    protected function makeTemplateAvailable(XlsformTemplate $record): XlsformTemplate
    {
        $record->available = true;
        $record->save();

        return $record;
    }
}
