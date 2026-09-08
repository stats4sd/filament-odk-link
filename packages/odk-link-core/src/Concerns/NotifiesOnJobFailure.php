<?php

namespace Stats4sd\FilamentOdkLink\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Throwable;

trait NotifiesOnJobFailure
{
    /**
     * @param  Model|Authenticatable|Collection<int, Model|Authenticatable>|array<int, Model|Authenticatable>  $recipients
     */
    public function notifyJobFailure(string $title, ?Throwable $exception, Model | Authenticatable | Collection | array $recipients, ?string $actionUrl = null): void
    {
        $notification = Notification::make()
            ->title($title)
            ->body(Str::limit($exception?->getMessage() ?? 'Unknown error', 200))
            ->danger()
            ->persistent();

        if ($actionUrl) {
            $notification->actions([
                Action::make('view')->url($actionUrl),
            ]);
        }

        foreach ($this->normaliseRecipients($recipients) as $recipient) {
            $notification
                ->sendToDatabase($recipient, isEventDispatched: true)
                ->broadcast($recipient);
        }
    }

    /** @return Collection<int, Model> */
    public function superAdmins(): Collection
    {
        $superAdminRole = Role::where('name', 'Super Admin')->first();

        if (! $superAdminRole) {
            return new Collection;
        }

        return $superAdminRole->users;
    }

    /**
     * @param  Model|Authenticatable|Collection<int, Model|Authenticatable>|array<int, Model|Authenticatable>  $recipients
     * @return Collection<int, Model|Authenticatable>
     */
    private function normaliseRecipients(Model | Authenticatable | Collection | array $recipients): Collection
    {
        if ($recipients instanceof Collection) {
            return $recipients;
        }

        if (is_array($recipients)) {
            return collect($recipients);
        }

        return collect([$recipients]);
    }
}
