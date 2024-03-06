<?php

namespace Stats4sd\FilamentOdkLink\Filament\Widgets;

use Filament\Widgets\Widget;

class OdkUrlAlertWidget extends Widget
{
    protected static string $view = 'filament-odk-link::filament.widgets.odk-url-alert-widget';

    protected int | string | array $columnSpan = 'full';
}
