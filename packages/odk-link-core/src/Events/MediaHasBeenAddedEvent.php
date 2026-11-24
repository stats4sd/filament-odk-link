<?php

namespace Stats4sd\OdkLink\Core\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent as SpatieMediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stats4sd\OdkLink\Core\Contracts\PlatformUser;
use Stats4sd\OdkLink\Core\Support\ConfiguredModels;

class MediaHasBeenAddedEvent extends SpatieMediaHasBeenAddedEvent
{
    use SerializesModels;

    public (Model & PlatformUser) | null $importedBy;

    public function __construct(public Media $media, (Model & PlatformUser) | null $importedBy = null)
    {
        parent::__construct($media);

        $this->importedBy = $importedBy ?? app(ConfiguredModels::class)->validateUser(auth()->user());
    }
}
