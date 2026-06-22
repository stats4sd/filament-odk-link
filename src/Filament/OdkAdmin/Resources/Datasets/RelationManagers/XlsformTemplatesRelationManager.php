<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\RelationManagers\RelationManager;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Schemas\XlsformTemplatesRelationManagerForm;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Tables\XlsformTemplatesRelationManagerTable;

class XlsformTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'xlsformTemplates';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Used in... ';
    }

    public function form(Schema $schema): Schema
    {
        return XlsformTemplatesRelationManagerForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return XlsformTemplatesRelationManagerTable::configure($table);
    }

}
