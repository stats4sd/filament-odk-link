<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkTeam\Widgets;

use Filament\Widgets\Widget;

class CustomOdkTemplatesWidget extends Widget
{
    protected int | string | array $columnSpan = 'full';

    /**
     * @var string
     */
    protected static string $view = 'filament-odk-link::filament.widgets.custom-odk-templates-widget';
}
