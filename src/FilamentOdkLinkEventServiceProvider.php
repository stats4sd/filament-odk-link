<?php

namespace Stats4sd\FilamentOdkLink;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Stats4sd\FilamentOdkLink\Listeners\HandleXlsformTemplateAdded;

class FilamentOdkLinkEventServiceProvider extends EventServiceProvider
{
    protected $listen = [
        MediaHasBeenAddedEvent::class => [
            HandleXlsformTemplateAdded::class,
        ],
    ];
}
