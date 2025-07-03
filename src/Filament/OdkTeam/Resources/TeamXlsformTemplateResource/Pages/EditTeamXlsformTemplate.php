<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplateResource\Pages;

use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource\Pages\EditXlsformTemplate;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamCustomXlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class EditTeamXlsformTemplate extends EditXlsformTemplate
{
    protected static string $resource = TeamXlsformTemplateResource::class;

    /**
     * @phpstan-return XlsformTemplate
     */
    public function getRecord(): Model | XlsformTemplate
    {
        /** @var XlsformTemplate $record */
        $record = parent::getRecord();

        return $record;
    }
}
