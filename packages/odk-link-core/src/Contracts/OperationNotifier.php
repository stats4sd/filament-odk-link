<?php

namespace Stats4sd\OdkLink\Core\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Stats4sd\OdkLink\Core\Support\OperationNotification;

interface OperationNotifier
{
    /** @param Collection<int, Model&PlatformUser> $recipients */
    public function send(OperationNotification $notification, Collection $recipients): void;
}
