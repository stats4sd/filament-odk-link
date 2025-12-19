<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplateResources\Pages;

use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Pages\EditXlsformTemplate;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplates\TeamXlsformTemplateResource;

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
