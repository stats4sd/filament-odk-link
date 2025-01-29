<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources;

use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplateResource\Pages\ListTeamXlsformTemplates;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

// Use this resource for a panel scoped to a team
// This resource is for templates available to all platform users

class TeamXlsformTemplateResource extends Resource
{
    protected static ?string $model = XlsformTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Available ODK Templates';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('available', true)
            ->where(function (Builder $query) {
                $query->whereHas('owner', function (Builder $subQuery) {
                    $subQuery->where('id', Filament::getTenant()->getKey());
                })
                    ->orWhere('owner_type', Platform::class);
            })
            ->orderBy('owner_type');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\IconColumn::make('has_version')
                    ->label('Deployed?')
                    ->state(fn (Xlsformtemplate $record) => $record->xlsforms->where('owner_id', Filament::getTenant()->getKey())->count() > 0)
                    ->boolean(),
                Tables\Columns\TextColumn::make('owner_type')
                    ->label('Owner')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        return $record->owner_type === Platform::class ? 'Platform' : 'Custom';
                    })
                    ->color(function ($record) {
                        return $record->owner_type === Platform::class ? 'gray' : 'success';
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([

                // TODO: setup a helper function that a) returns the current tenant as a "WithXlsforms" class, and b) makes sure that devs realise the Filament tenant must implement this interface.
                Tables\Actions\Action::make('deploy')
                    ->label('Deploy Form')
                    ->hidden(fn (Xlsformtemplate $record) => $record->xlsforms->where('owner_id', Filament::getTenant()->getKey())->count() > 0)
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->form([
                        Forms\Components\TextInput::make('title')
                            ->label('Please give the form a title.')
                            ->default(fn (XlsformTemplate $record) => Filament::getTenant()->name . ' - ' . $record->title)
                            ->hint('Note that ODK form titles cannot be longer than 64 characters.'),
                    ])
                    ->action(function (XlsformTemplate $record, array $data) {

                        $xlsform = $record->xlsforms()->create([
                            'owner_id' => Filament::getTenant()->getKey(),
                            'owner_type' => config('filament-odk-link.models.team_model'),
                            'title' => $data['title'],
                        ]);

                        // publish the new form
                        $xlsform->refresh();
                        $xlsform->publishForm(app()->make(OdkLinkService::class));

                    }),
                Tables\Actions\Action::make('download file')
                    ->label('Download XLS File')
                    ->url(fn ($record) => $record->getFirstMediaUrl('xlsform_file')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeamXlsformTemplates::route('/'),
        ];
    }
}
