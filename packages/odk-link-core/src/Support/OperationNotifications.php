<?php

namespace Stats4sd\FilamentOdkLink\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Stats4sd\FilamentOdkLink\Contracts\OperationNotifier;
use Stats4sd\FilamentOdkLink\Contracts\PlatformUser;
use Throwable;

class OperationNotifications
{
    public function __construct(private NotificationRecipients $recipients) {}

    /** @param Collection<int, Model&PlatformUser> $recipients */
    public function send(OperationNotification $notification, Collection $recipients): void
    {
        $recipients = $this->recipients->validate($recipients);

        if ($recipients->isEmpty()) {
            return;
        }

        app(OperationNotifier::class)->send($notification, $recipients);
    }

    public function failure(OperationNotification $notification, (Model & PlatformUser) | null $actor = null): void
    {
        try {
            $this->send($notification, $this->recipients->forActor($actor));
        } catch (Throwable $exception) {
            Log::error('Failed to deliver an ODK Link operation failure notification.', ['exception' => $exception]);
        }
    }
}
