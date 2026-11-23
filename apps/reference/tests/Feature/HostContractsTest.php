<?php

use App\Models\Team;
use App\Models\User;
use App\OdkLink\ReferenceRoleResolver;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Stats4sd\FilamentOdkLink\Contracts\CurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Contracts\OperationNotifier;
use Stats4sd\FilamentOdkLink\Contracts\RoleResolver;
use Stats4sd\FilamentOdkLink\Contracts\SubmissionProcessor;
use Stats4sd\FilamentOdkLink\Events\XlsformModuleVersionWasImported;
use Stats4sd\FilamentOdkLink\Filament\Support\FilamentCurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Filament\Support\FilamentOperationNotifier;
use Stats4sd\FilamentOdkLink\Jobs\FinishXlsformTemplateImport;
use Stats4sd\FilamentOdkLink\Jobs\OdkSubmissions\ProcessOdkSubmission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformVersion;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Stats4sd\FilamentOdkLink\Support\ConfiguredModels;
use Stats4sd\FilamentOdkLink\Support\CurrentOwner;
use Stats4sd\FilamentOdkLink\Support\OperationNotification;

class ReferenceSubmissionRecorder
{
    public ?Submission $received = null;
}

class ReferenceTestProcessor implements SubmissionProcessor
{
    public function __construct(private ReferenceSubmissionRecorder $recorder) {}

    public function process(Submission $submission): void
    {
        $this->recorder->received = $submission;
    }
}

it('resolves host contracts and UI defaults in a worker without visiting a panel', function () {
    expect(app(ConfiguredModels::class)->formOwnerClass())->toBe(Team::class)
        ->and(app(ConfiguredModels::class)->userClass())->toBe(User::class)
        ->and(app(RoleResolver::class))->toBeInstanceOf(ReferenceRoleResolver::class)
        ->and(app(CurrentOwnerResolver::class))->toBeInstanceOf(FilamentCurrentOwnerResolver::class)
        ->and(app(OperationNotifier::class))->toBeInstanceOf(FilamentOperationNotifier::class)
        ->and(app(CurrentOwner::class)->current())->toBeNull();
});

it('selects no recipients before the host administrator role exists', function () {
    User::factory()->create();
    expect(app(RoleResolver::class)->notificationRecipients())->toBeEmpty();
});

it('finishes an imported module and routes its notification through the hosts administrator policy', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Super Admin', 'web'));
    User::factory()->create();
    $notifier = Mockery::mock(OperationNotifier::class);
    $notifier->shouldReceive('send')->once()->withArgs(fn (OperationNotification $notification, Collection $recipients) => $notification->id === 'xlsform_module_version_imported' && ! $notification->database && $notification->broadcast && $recipients->pluck('id')->all() === [$admin->id]);
    app()->instance(OperationNotifier::class, $notifier);
    Event::fake([XlsformModuleVersionWasImported::class]);
    $templateId = DB::table('xlsform_templates')->insertGetId(['title' => 'Imported fixture']);
    $moduleId = DB::table('xlsform_modules')->insertGetId(['xlsform_template_id' => $templateId, 'name' => 'module', 'label' => 'Module']);
    $versionId = DB::table('xlsform_module_versions')->insertGetId(['xlsform_module_id' => $moduleId, 'name' => 'Questions', 'processing' => true]);
    $version = XlsformModuleVersion::findOrFail($versionId);

    (new FinishXlsformTemplateImport($version))->handle();

    expect((bool) $version->fresh()->processing)->toBeFalse();
    Event::assertDispatched(XlsformModuleVersionWasImported::class);
});

it('resolves a container injected processor after the core has processed the submission', function () {
    $recorder = new ReferenceSubmissionRecorder;
    app()->instance(ReferenceSubmissionRecorder::class, $recorder);
    config()->set('filament-odk-link.contracts.submission_processor', ReferenceTestProcessor::class);
    $service = Mockery::mock(OdkLinkService::class);
    $service->shouldReceive('processSubmission')->once()->andReturnUsing(function (Submission $submission) {
        $submission->content = ['ingested' => true];
    });
    app()->instance(OdkLinkService::class, $service);
    $submission = new Submission;

    (new ProcessOdkSubmission($submission, [], new XlsformVersion))->handle();

    expect($recorder->received)->toBe($submission)->and($recorder->received->content)->toBe(['ingested' => true]);
});

it('reads the selected tenant on every current owner resolution', function () {
    $this->actingAs(User::factory()->create());
    $first = Team::factory()->create();
    $second = Team::factory()->create();
    Filament::setCurrentPanel(Filament::getPanel('team'));
    Filament::setTenant($first);
    expect(app(CurrentOwner::class)->current()->is($first))->toBeTrue();
    Filament::setTenant($second);
    expect(app(CurrentOwner::class)->current()->is($second))->toBeTrue();
    Filament::setTenant(null);
    expect(app(CurrentOwner::class)->current())->toBeNull();
});
