<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources;

use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplateResource\Pages\CreateTeamXlsformTemplate;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplateResource\Pages\EditTeamXlsformTemplate;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplateResource\Pages\ListTeamXlsformTemplates;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplateResource\Pages\ViewTeamXlsformTemplate;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsformTemplates;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\HelperService;

// Use this resource for a panel scoped to a team
// This resource is for templates available to all platform users

class TeamXlsformTemplateResource extends XlsformTemplateResource
{
    protected static ?string $model = XlsformTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'ODK Form Templates';

    protected static ?int $navigationSort = 100;

    protected static bool $isScopedToTenant = false;

    protected static WithXlsformTemplates $formOwner;

    public static function getFormOwner(): WithXlsformTemplates
    {
        return Filament::getTenant();
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Check if the plugin has register navigation set on the current panel
        return Filament::getCurrentPanel()->getPlugin('stats4sd-odk-link-team')->getShouldRegisterNavigation();
    }

    public static function getEloquentQuery(): Builder
    {
        // get all teamplates that
        //   - belong to the current tenant
        //   - OR belong to the platform _and_ are available

        return parent::getEloquentQuery()
            ->where(function (Builder $query) {
                $query
                    ->whereHasMorph(
                        relation: 'owner',
                        types: [get_class(Filament::getTenant())],
                        callback: function (Builder $subQuery) {
                            $subQuery->where('id', Filament::getTenant()->getKey());
                        }
                    )
                    ->orWhere(function (Builder $query) {
                        $query
                            ->whereHasMorph(
                                relation: 'owner',
                                types: [Platform::class]
                            )
                            ->where('available', true);
                    });
            })
            ->orderBy('owner_type');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\IconColumn::make('has_version')
                    ->label('In use?')
                    ->state(fn (Xlsformtemplate $record) => $record->xlsforms->where('owner_id', Filament::getTenant()->getKey())->count() > 0)
                    ->boolean(),
                Tables\Columns\TextColumn::make('owner_type')
                    ->label('Source')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        return $record->owner_type === Platform::class ? 'Global' : 'Project';
                    })
                    ->color(function ($record) {
                        return $record->owner_type === Platform::class ? 'gray' : 'success';
                    }),
                Tables\Columns\ViewColumn::make('required_fixed_media_count')
                    ->label('Fixed Media')
                    ->view('filament-odk-link::filament.tables.columns.required-fixed-media-count'),
                Tables\Columns\ViewColumn::make('required_data_media_count')
                    ->label('Datasets')
                    ->view('filament-odk-link::filament.tables.columns.required-data-media-count'),
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
                            ->default(fn (XlsformTemplate $record) => HelperService::getCurrentOwner()->name.' - '.$record->title)
                            ->hint('Note that ODK form titles cannot be longer than 64 characters.'),
                    ])
                    ->action(function (XlsformTemplate $record, array $data) {

                        $xlsform = $record->xlsforms()->create([
                            'owner_id' => Filament::getTenant()->getKey(),
                            'owner_type' => config('filament-odk-link.models.form_owner'),
                            'title' => $data['title'],
                        ]);

                        // publish the new form
                        $xlsform->refresh();
                        $xlsform->publishForm();

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

    public static function infoList(Infolist $infolist): Infolist
    {
        return XlsformTemplateResource::infoList($infolist);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeamXlsformTemplates::route('/'),
            'create' => CreateTeamXlsformTemplate::route('/create'),
            'edit' => EditTeamXlsformTemplate::route('/{record}/edit'),
            'view' => ViewTeamXlsformTemplate::route('/{record}'),
        ];
    }
}
