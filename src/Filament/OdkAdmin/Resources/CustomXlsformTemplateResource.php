<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources;

use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Stats4sd\FilamentOdkLink\Filament\Resources\CustomXlsformTemplateResource\Pages;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

// Use this resource for an admin panel
// This resource shows all custom templates which are owned by specific teams

class CustomXlsformTemplateResource extends Resource
{
    protected static ?string $model = XlsformTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $slug = 'custom-xlsform-templates';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('owner_type', '!=', Platform::class);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Owner')
                    ->url(fn ($record) => "/app/{$record->owner->slug}")
                    ->openUrlInNewTab()
                    ->color('primary'),
                Tables\Columns\IconColumn::make('has_version')
                    ->label('Deployed?')
                    ->state(fn (Xlsformtemplate $record) => $record->xlsforms->count() > 0)
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('View template')
                    ->url(fn ($record) => "/app/{$record->owner->slug}/xlsforms/custom-xlsform-templates")
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-eye'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\CustomXlsformTemplateResource\Pages\ListCustomXlsformTemplates::route('/'),
        ];
    }
}
