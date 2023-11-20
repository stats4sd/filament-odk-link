<?php

namespace Stats4sd\FilamentOdkLink\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Stats4sd\FilamentOdkLink\FilamentOdkLink
 */
class FilamentOdkLink extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Stats4sd\FilamentOdkLink\FilamentOdkLink::class;
    }
}
