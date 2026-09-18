<?php

namespace Stats4sd\FilamentOdkLink\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Contracts\PlatformUser;
use Stats4sd\FilamentOdkLink\Support\OperationNotification;
use Stats4sd\FilamentOdkLink\Support\OperationNotifications;
use Throwable;

trait NotifiesOnJobFailure
{
    public function notifyJobFailure(string $title, ?Throwable $exception, (Model & PlatformUser) | null $actor = null, ?string $actionUrl = null): void
    {
        app(OperationNotifications::class)->failure(new OperationNotification(
            title: $title,
            body: Str::limit($exception?->getMessage() ?? 'Unknown error', 200),
            severity: 'danger',
            persistent: true,
            database: true,
            actionUrl: $actionUrl,
        ), $actor);
    }
}
