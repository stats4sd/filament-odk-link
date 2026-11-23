<?php

namespace Stats4sd\FilamentOdkLink\Filament\Support;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Contracts\OperationNotifier;
use Stats4sd\FilamentOdkLink\Support\OperationNotification;

class FilamentOperationNotifier implements OperationNotifier
{
    public function send(OperationNotification $notification, Collection $recipients): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        $message = Notification::make($notification->id)
            ->title($notification->title)
            ->body($notification->body)
            ->status($notification->severity);

        if ($notification->persistent) {
            $message->persistent();
        }

        if ($notification->actionUrl !== null) {
            $message->actions([Action::make('view')->url($notification->actionUrl)]);
        }

        foreach ($recipients as $recipient) {
            if ($notification->database) {
                $message->sendToDatabase($recipient, isEventDispatched: true);
            }

            if ($notification->broadcast) {
                $message->broadcast($recipient);
            }
        }
    }
}
