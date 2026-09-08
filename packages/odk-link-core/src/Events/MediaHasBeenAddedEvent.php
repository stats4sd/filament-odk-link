<?php

namespace Stats4sd\FilamentOdkLink\Events;

use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stats4sd\FilamentTeamManagement\Models\User;

class MediaHasBeenAddedEvent extends \Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent
{
    use SerializesModels;

    public function __construct(public Media $media, public ?User $importedBy)
    {
        parent::__construct($media);

        $this->importedBy = $importedBy ?? auth()->user();
    }
}
