<?php

namespace Stats4sd\FilamentOdkLink\Filament\Widgets;

use Filament\Widgets\Widget;

class AvailableOdkTemplatesWidget extends Widget
{
    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament-odk-link::filament.widgets.available-templates-widget';
}
