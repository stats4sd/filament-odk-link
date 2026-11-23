<?php

namespace Stats4sd\FilamentOdkLink\Support;

use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Contracts\OperationNotifier;

class NullOperationNotifier implements OperationNotifier
{
    public function send(OperationNotification $notification, Collection $recipients): void {}
}
