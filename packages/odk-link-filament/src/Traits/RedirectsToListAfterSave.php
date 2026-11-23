<?php

namespace Stats4sd\OdkLink\Filament\Traits;

trait RedirectsToListAfterSave
{
    // redirect back to index unless that is impossible
    protected function getRedirectUrl(): string
    {
        $resource = static::getResource();

        return $resource::getUrl('index');
    }
}
