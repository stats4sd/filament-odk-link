<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Contracts\OperationNotifier;
use Stats4sd\FilamentOdkLink\Contracts\RoleResolver;
use Stats4sd\FilamentOdkLink\Events\XlsformModuleVersionWasImported;
use Stats4sd\FilamentOdkLink\Exports\XlsformExport\XlsformWorkbookExport;
use Stats4sd\FilamentOdkLink\Jobs\FinishXlsformTemplateImport;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\DeployDraftXlsformToOdkCentral;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\NotifyUserThatXlsformFileIsDeployedAsDraft;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\NotifyUserThatXlsformFileIsUpdated;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\PublishXlsformOnOdkCentral;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\UpdateXlsformFile;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Support\OperationNotification;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;
use Stats4sd\FilamentOdkLink\Tests\Models\User;

uses(RefreshDatabase::class);

class CapturedOperationNotifications implements OperationNotifier
{
    public array $deliveries = [];

    public function send(OperationNotification $notification, Collection $recipients): void
    {
        $this->deliveries[] = [$notification, $recipients];
    }
}

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    $this->notifier = new CapturedOperationNotifications;
    app()->instance(OperationNotifier::class, $this->notifier);
    $this->actor = User::create(['name' => 'Initiator']);
    $this->owner = Team::createQuietly(['name' => 'Alternate host']);
    $template = makeXlsformTemplate();
    $id = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $template->id,
        'owner_id' => $this->owner->id,
        'title' => 'Field survey',
        'processing' => true,
    ]);
    $this->form = Xlsform::findOrFail($id);
});

it('serializes a host actor and delivers the original broadcast-only success payload', function () {
    $job = unserialize(serialize(new NotifyUserThatXlsformFileIsUpdated($this->form, $this->actor)));
    $job->handle();
    [$notification, $recipients] = $this->notifier->deliveries[0];
    expect($recipients->sole()->is($this->actor))->toBeTrue()
        ->and($notification->id)->toBe('xlsform_file_updated')
        ->and($notification->title)->toBe('Xlsform File Updated')
        ->and($notification->body)->toBe('The Xlsform Field survey belonging to Alternate host has been updated.')
        ->and($notification->severity)->toBe('success')
        ->and($notification->database)->toBeFalse()
        ->and($notification->broadcast)->toBeTrue();
});

it('retains missing-model failure for deleted queued actors without an administrator fallback', function (string $jobClass) {
    $job = match ($jobClass) {
        DeployDraftXlsformToOdkCentral::class => new $jobClass($this->form, true, $this->actor),
        UpdateXlsformFile::class => new $jobClass($this->form, 'queued.xlsx', $this->actor),
        default => new $jobClass($this->form, $this->actor),
    };
    $payload = serialize($job);
    $this->actor->delete();
    expect(fn () => unserialize($payload))->toThrow(ModelNotFoundException::class)
        ->and($this->notifier->deliveries)->toBeEmpty();
})->with([
    NotifyUserThatXlsformFileIsUpdated::class,
    NotifyUserThatXlsformFileIsDeployedAsDraft::class,
    DeployDraftXlsformToOdkCentral::class,
    PublishXlsformOnOdkCentral::class,
    UpdateXlsformFile::class,
]);

it('clears processing and delivers deployment failures to the initiating actor first', function (string $jobClass) {
    app()->instance(RoleResolver::class, new class implements RoleResolver
    {
        public function notificationRecipients(): Collection
        {
            throw new RuntimeException('Administrators must not be resolved for an initiating user');
        }
    });
    $job = $jobClass === DeployDraftXlsformToOdkCentral::class
        ? new $jobClass($this->form, true, $this->actor)
        : new $jobClass($this->form, $this->actor);
    $job->failed(new RuntimeException('Original deployment error'));
    [$notification, $recipients] = $this->notifier->deliveries[0];
    expect($this->form->fresh()->processing)->toBe(0)
        ->and($recipients->sole()->is($this->actor))->toBeTrue()
        ->and($notification->id)->toBe('xlsform_file_deployment_failed')
        ->and($notification->body)->toBeInstanceOf(HtmlString::class)
        ->and((string) $notification->body)->toContain('Original deployment error')
        ->and($notification->database)->toBeTrue()
        ->and($notification->broadcast)->toBeTrue()
        ->and($notification->persistent)->toBeTrue();
})->with([DeployDraftXlsformToOdkCentral::class, PublishXlsformOnOdkCentral::class]);

it('contains secondary recipient or transport failures after processing cleanup', function (string $failure) {
    Log::spy();
    if ($failure === 'recipients') {
        app()->instance(RoleResolver::class, new class implements RoleResolver
        {
            public function notificationRecipients(): Collection
            {
                throw new RuntimeException('Recipient service down');
            }
        });
    } else {
        app()->instance(OperationNotifier::class, new class implements OperationNotifier
        {
            public function send(OperationNotification $notification, Collection $recipients): void
            {
                throw new RuntimeException('Transport down');
            }
        });
    }
    $actor = $failure === 'recipients' ? null : $this->actor;
    (new PublishXlsformOnOdkCentral($this->form, $actor))->failed(new RuntimeException('Original failure'));
    expect($this->form->fresh()->processing)->toBe(0);
    Log::shouldHaveReceived('error')->with('Xlsform Publishing Failed', Mockery::on(fn ($context) => $context['exception']->getMessage() === 'Original failure'))->once();
    Log::shouldHaveReceived('error')->with('Failed to deliver an ODK Link operation failure notification.', Mockery::type('array'))->once();
})->with(['recipients', 'transport']);

it('finishes imports and emits domain events with no administrators', function () {
    Event::fake([XlsformModuleVersionWasImported::class]);
    $module = addModuleVersion(makeXlsformTemplate(), 'Questions');
    $module->updateQuietly(['processing' => true]);
    (new FinishXlsformTemplateImport($module))->handle();
    expect($module->fresh()->processing)->toBe(0)
        ->and($this->notifier->deliveries)->toBeEmpty();
    Event::assertDispatched(XlsformModuleVersionWasImported::class);
});

it('delivers import completion through host recipients with duplicates removed', function () {
    $actor = $this->actor;
    app()->instance(RoleResolver::class, new class($actor) implements RoleResolver
    {
        public function __construct(private User $actor) {}

        public function notificationRecipients(): Collection
        {
            return collect([$this->actor, clone $this->actor]);
        }
    });
    $module = addModuleVersion(makeXlsformTemplate(), 'Questions');
    (new FinishXlsformTemplateImport($module))->handle();
    [$notification, $recipients] = $this->notifier->deliveries[0];
    expect($recipients)->toHaveCount(1)
        ->and($notification->id)->toBe('xlsform_module_version_imported')
        ->and($notification->database)->toBeFalse();
});

it('rejects invalid authenticated actors before marking forms as processing', function () {
    $this->form->updateQuietly(['processing' => false]);
    auth()->setUser(new Illuminate\Foundation\Auth\User);
    Bus::fake();
    expect(fn () => $this->form->generateXlsfile())->toThrow(InvalidArgumentException::class, 'models.user_model');
    expect($this->form->fresh()->processing)->toBe(0);
    Bus::assertNothingDispatched();
});

it('preserves export failure truncation and actor delivery after cleanup', function () {
    $message = str_repeat('X', 250);
    (new XlsformWorkbookExport($this->form, $this->actor))->failed(new RuntimeException($message));
    [$notification, $recipients] = $this->notifier->deliveries[0];
    expect($notification->body)->toBe(Str::limit($message, 200))
        ->and($notification->title)->toBe('Form file generation failed: Field survey')
        ->and($notification->database)->toBeTrue()
        ->and($recipients->sole()->is($this->actor))->toBeTrue()
        ->and($this->form->fresh()->processing)->toBe(0);
});

it('propagates success transport failures after import completion', function () {
    app()->instance(OperationNotifier::class, new class implements OperationNotifier
    {
        public function send(OperationNotification $notification, Collection $recipients): void
        {
            throw new RuntimeException('Transport failure');
        }
    });
    $actor = $this->actor;
    app()->instance(RoleResolver::class, new class($actor) implements RoleResolver
    {
        public function __construct(private User $actor) {}

        public function notificationRecipients(): Collection
        {
            return collect([$this->actor]);
        }
    });
    $module = addModuleVersion(makeXlsformTemplate(), 'Questions');
    $module->updateQuietly(['processing' => true]);
    expect(fn () => (new FinishXlsformTemplateImport($module))->handle())->toThrow(RuntimeException::class, 'Transport failure');
    expect($module->fresh()->processing)->toBe(0);
});
