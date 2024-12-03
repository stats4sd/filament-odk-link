<?php

namespace Stats4sd\FilamentOdkLink\Filament\Widgets;

use Filament\Widgets\Widget;

class CustomOdkTemplatesWidget extends Widget
{
    protected int | string | array $columnSpan = 'full';
    protected static string $view = 'filament-odk-link::filament.widgets.custom-odk-templates-widget';
}
