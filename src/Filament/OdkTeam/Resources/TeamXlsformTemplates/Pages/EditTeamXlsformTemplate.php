<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplates\Pages;

use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Pages\EditXlsformTemplate;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplates\TeamXlsformTemplateResource;


// Extend the admin panel version to get the shared functionality for handling datasets, but override the resource to use the team-scoped one
class EditTeamXlsformTemplate extends \App\Filament\Admin\Resources\XlsformTemplates\Pages\EditXlsformTemplate
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
