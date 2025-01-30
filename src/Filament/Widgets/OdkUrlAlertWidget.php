<?php

namespace Stats4sd\FilamentOdkLink\Filament\Widgets;

use Filament\Widgets\Widget;

class OdkUrlAlertWidget extends Widget
{
    /** @phpstan-ignore-next-line
     * (because it's looking at the Widget definition and asking for a "view-string", which doesn't seem to exist)
     */
    protected static string $view = 'filament-odk-link::filament.widgets.odk-url-alert-widget';

    protected int | string | array $columnSpan = 'full';
}
