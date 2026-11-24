<?php

namespace Stats4sd\OdkLink\Core;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Stats4sd\OdkLink\Core\Listeners\HandleXlsformTemplateAdded;

class OdkLinkEventServiceProvider extends EventServiceProvider
{
    protected $listen = [
        MediaHasBeenAddedEvent::class => [
            HandleXlsformTemplateAdded::class,
        ],
    ];
}
