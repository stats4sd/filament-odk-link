<?php

namespace Stats4sd\OdkLink\Core\Support;

use Illuminate\Support\Collection;
use Stats4sd\OdkLink\Core\Contracts\OperationNotifier;

class NullOperationNotifier implements OperationNotifier
{
    public function send(OperationNotification $notification, Collection $recipients): void {}
}
