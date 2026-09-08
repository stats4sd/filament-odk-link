<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\TeamPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    TeamPanelProvider::class,
];
