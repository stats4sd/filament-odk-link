<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkTeam\Widgets;

use Filament\Widgets\Widget;

class CustomOdkTemplatesWidget extends Widget
{
    protected int | string | array $columnSpan = 'full';

    /** @phpstan-ignore-next-line  */
    protected static string $view = 'filament-odk-link::filament.widgets.custom-odk-templates-widget';

}
