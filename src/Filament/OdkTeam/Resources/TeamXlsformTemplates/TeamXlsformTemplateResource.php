<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplates;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsformTemplates;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\XlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Schemas\XlsformTemplateInfolist;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplates\Pages\EditTeamXlsformTemplate;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplates\Pages\ViewTeamXlsformTemplate;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplates\Pages\ListTeamXlsformTemplates;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplates\Pages\CreateTeamXlsformTemplate;

// Use this resource for a panel scoped to a team
// This resource is for templates available to all platform users

class TeamXlsformTemplateResource extends XlsformTemplateResource
{
    protected static ?string $model = XlsformTemplate::class;

    protected static string | null | BackedEnum $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'ODK Form Templates';

    protected static ?int $navigationSort = 100;

    protected static bool $isScopedToTenant = false;

    protected static WithXlsformTemplates $formOwner;

    public static function getFormOwner(): WithXlsformTemplates
    {
        return Filament::getTenant();
    }

    // public static function shouldRegisterNavigation(): bool
    // {
    //     // Check if the plugin has register navigation set on the current panel
    //     return Filament::getCurrentPanel()->getPlugin('stats4sd-odk-link-team')->getShouldRegisterNavigation();
    // }

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

    public static function infolist(Schema $schema): Schema
    {
        return XlsformTemplateInfolist::configure($schema);
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
