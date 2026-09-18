<?php

namespace Stats4sd\FilamentOdkLink\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Stats4sd\FilamentOdkLink\Contracts\PlatformUser;
use Stats4sd\FilamentOdkLink\Contracts\RoleResolver;

class NotificationRecipients
{
    public function __construct(private ConfiguredModels $models) {}

    /** @return Collection<int, Model&PlatformUser> */
    public function forActor((Model & PlatformUser) | null $actor): Collection
    {
        return $actor === null ? $this->administrators() : $this->validate(collect([$actor]));
    }

    /** @return Collection<int, Model&PlatformUser> */
    public function administrators(): Collection
    {
        return $this->validate(app(RoleResolver::class)->notificationRecipients());
    }

    /**
     * @param  Collection<int, mixed>  $recipients
     * @return Collection<int, Model&PlatformUser>
     */
    public function validate(Collection $recipients): Collection
    {
        return $recipients->map(function ($recipient) {
            if ($recipient === null) {
                throw new InvalidArgumentException('filament-odk-link.models.user_model notification recipients cannot contain null.');
            }

            return $this->models->validateUser($recipient);
        })->unique(fn (Model $recipient) => $recipient->getKey() === null
            ? 'unsaved:' . spl_object_id($recipient)
            : $recipient::class . ':' . $recipient->getConnectionName() . ':' . $recipient->getKey())->values();
    }
}
