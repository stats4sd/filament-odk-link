<?php

use Filament\Facades\Filament;
use Filament\Notifications\BroadcastNotification;
use Filament\Notifications\DatabaseNotification;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Panel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\HtmlString;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Stats4sd\FilamentOdkLink\Commands\PollForOdkData;
use Stats4sd\FilamentOdkLink\Contracts\CurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Contracts\OperationNotifier;
use Stats4sd\FilamentOdkLink\Filament\Support\FilamentCurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Filament\Support\FilamentOperationNotifier;
use Stats4sd\FilamentOdkLink\FilamentOdkLinkEventServiceProvider;
use Stats4sd\FilamentOdkLink\FilamentOdkLinkServiceProvider;
use Stats4sd\FilamentOdkLink\Listeners\HandleXlsformTemplateAdded;
use Stats4sd\FilamentOdkLink\OdkLinkCoreServiceProvider;
use Stats4sd\FilamentOdkLink\Support\CurrentOwner;
use Stats4sd\FilamentOdkLink\Support\NullCurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Support\NullOperationNotifier;
use Stats4sd\FilamentOdkLink\Support\OperationNotification;
use Stats4sd\FilamentOdkLink\Support\OperationNotifications;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;
use Stats4sd\FilamentOdkLink\Tests\Models\User;

it('provides lazy Filament defaults in workers and respects explicit overrides', function () {
    expect(app(CurrentOwnerResolver::class))->toBeInstanceOf(FilamentCurrentOwnerResolver::class)
        ->and(app(CurrentOwner::class)->current())->toBeNull()
        ->and(app(OperationNotifier::class))->toBeInstanceOf(FilamentOperationNotifier::class);
    config()->set('filament-odk-link.contracts.current_owner_resolver', NullCurrentOwnerResolver::class);
    config()->set('filament-odk-link.contracts.operation_notifier', NullOperationNotifier::class);
    expect(app(CurrentOwnerResolver::class))->toBeInstanceOf(NullCurrentOwnerResolver::class)
        ->and(app(OperationNotifier::class))->toBeInstanceOf(NullOperationNotifier::class);
});

it('reads each selected tenant afresh and rejects incompatible selected tenants', function () {
    Filament::setCurrentPanel(Panel::make()->id('team')->tenant(Team::class));
    $first = Team::createQuietly(['name' => 'First']);
    $second = Team::createQuietly(['name' => 'Second']);
    $owner = app(CurrentOwner::class);
    Filament::setTenant($first, isQuiet: true);
    expect($owner->current()->is($first))->toBeTrue();
    Filament::setTenant($second, isQuiet: true);
    expect($owner->current()->is($second))->toBeTrue();
    Filament::setTenant(null, isQuiet: true);
    expect($owner->current())->toBeNull();
    Filament::setTenant(new User, isQuiet: true);
    expect(fn () => $owner->current())->toThrow(InvalidArgumentException::class, 'models.form_owner');
});

it('renders a success broadcast with no database notification', function () {
    Notification::fake();
    $user = (new User)->forceFill(['id' => 1]);
    app(OperationNotifications::class)->send(new OperationNotification(title: 'Ready', body: 'Ready to test', severity: 'success', id: 'ready'), collect([$user]));
    Notification::assertSentTo($user, BroadcastNotification::class, fn ($message) => $message->data['id'] === 'ready' && $message->data['title'] === 'Ready' && $message->data['status'] === 'success');
    Notification::assertNotSentTo($user, DatabaseNotification::class);
});

it('renders durable failure HTML with the view action and database refresh event', function () {
    Notification::fake();
    Event::fake([DatabaseNotificationsSent::class]);
    $user = (new User)->forceFill(['id' => 1]);
    app(OperationNotifications::class)->send(new OperationNotification(
        title: 'Failed',
        body: new HtmlString('Details<br/>Failure'),
        severity: 'danger',
        id: 'failed',
        persistent: true,
        database: true,
        actionUrl: 'https://example.test/forms/1',
    ), collect([$user, clone $user]));
    Notification::assertSentToTimes($user, DatabaseNotification::class, 1);
    Notification::assertSentToTimes($user, BroadcastNotification::class, 1);
    Notification::assertSentTo($user, DatabaseNotification::class, function ($message) {
        expect($message->data['body'])->toBe('Details<br/>Failure')
            ->and($message->data['actions'][0]['name'])->toBe('view')
            ->and($message->data['actions'][0]['url'])->toBe('https://example.test/forms/1')
            ->and($message->data['duration'])->toBe('persistent');

        return true;
    });
    Event::assertDispatchedTimes(DatabaseNotificationsSent::class, 1);
});

it('ignores a retained tenant when switching to an administrative panel', function () {
    $team = Team::createQuietly(['name' => 'Tenant']);
    Filament::setCurrentPanel(Panel::make()->id('team')->tenant(Team::class));
    Filament::setTenant($team, isQuiet: true);
    expect(app(CurrentOwner::class)->current()->is($team))->toBeTrue();
    Filament::setCurrentPanel(Panel::make()->id('admin'));
    expect(app(CurrentOwner::class)->current())->toBeNull();
});

it('registers package event listeners and console commands once through the combined provider', function () {
    app()->register(OdkLinkCoreServiceProvider::class);
    app()->register(FilamentOdkLinkServiceProvider::class);
    $listeners = Event::getRawListeners()[MediaHasBeenAddedEvent::class];
    expect(collect($listeners)->filter(fn ($listener) => $listener === HandleXlsformTemplateAdded::class))->toHaveCount(1);
    $commands = Artisan::all();
    expect(collect($commands)->filter(fn ($command) => $command instanceof PollForOdkData))->toHaveCount(1)
        ->and($commands)->toHaveKey('filament-odk-link:install')
        ->and(app()->getProviders(OdkLinkCoreServiceProvider::class))->toHaveCount(1)
        ->and(app()->getProviders(FilamentOdkLinkEventServiceProvider::class))->toHaveCount(1);
});
