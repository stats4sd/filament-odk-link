<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Contracts\CurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Contracts\FormOwner;
use Stats4sd\FilamentOdkLink\Contracts\RoleResolver;
use Stats4sd\FilamentOdkLink\Models\Country;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;
use Stats4sd\FilamentOdkLink\Support\ConfiguredModelRegistry;
use Stats4sd\FilamentOdkLink\Support\ConfiguredModels;
use Stats4sd\FilamentOdkLink\Support\CurrentOwner;
use Stats4sd\FilamentOdkLink\Support\NotificationRecipients;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;
use Stats4sd\FilamentOdkLink\Tests\Models\User;

class OtherFormOwner extends Model implements FormOwner
{
    use HasXlsforms;
}

class SameTeamTable extends Model
{
    protected $table = 'teams';
}

class RegistryNeedsArgument extends Model
{
    public function __construct(string $required) {}
}

class RecipientDependency
{
    public function users(): Collection
    {
        return collect([(new User)->forceFill(['id' => 7])]);
    }
}

class InjectedRecipients implements RoleResolver
{
    public function __construct(private RecipientDependency $dependency) {}

    public function notificationRecipients(): Collection
    {
        return $this->dependency->users();
    }
}

it('validates configured models and rejects incompatible actors', function () {
    expect(app(ConfiguredModels::class)->formOwnerClass())->toBe(Team::class)
        ->and(app(ConfiguredModels::class)->userClass())->toBe(User::class);
    expect(fn () => app(ConfiguredModels::class)->validateUser(new Team))->toThrow(InvalidArgumentException::class, 'models.user_model');
    config()->set('filament-odk-link.models.form_owner', Country::class);
    expect(fn () => app(ConfiguredModels::class)->formOwnerClass())->toThrow(InvalidArgumentException::class, 'FormOwner');
});

it('validates custom owner resolver results against the configured class', function () {
    app()->instance(CurrentOwnerResolver::class, new class implements CurrentOwnerResolver
    {
        public function current(): (Model & FormOwner) | null
        {
            return (new OtherFormOwner)->forceFill(['id' => 1]);
        }
    });
    expect(fn () => app(CurrentOwner::class)->current())->toThrow(InvalidArgumentException::class, Team::class);
});

it('resolves configured service dependencies and rejects invalid explicit classes', function () {
    config()->set('filament-odk-link.contracts.role_resolver', InjectedRecipients::class);
    expect(app(NotificationRecipients::class)->administrators()->sole()->id)->toBe(7);
    config()->set('filament-odk-link.contracts.role_resolver', Team::class);
    expect(fn () => app(RoleResolver::class))->toThrow(InvalidArgumentException::class, 'contracts.role_resolver');
});

it('validates and deduplicates recipients while preserving actor precedence', function () {
    $user = (new User)->forceFill(['id' => 2]);
    $recipients = app(NotificationRecipients::class);
    expect($recipients->validate(collect([$user, clone $user])))->toHaveCount(1);
    app()->instance(RoleResolver::class, new class implements RoleResolver
    {
        public function notificationRecipients(): Collection
        {
            throw new RuntimeException('Must not resolve administrators for an actor');
        }
    });
    expect($recipients->forActor($user)->sole())->toBe($user);
    expect(fn () => $recipients->validate(collect([new Team])))->toThrow(InvalidArgumentException::class, 'models.user_model');
});

it('resolves explicit registry classes and rejects ambiguity on both public paths', function () {
    config()->set('filament-odk-link.models.registry', [Country::class, Team::class, Team::class]);
    $registry = app(ConfiguredModelRegistry::class);
    expect($registry->classes()->all())->toBe([Country::class, Team::class])
        ->and($registry->findByTable('teams'))->toBeInstanceOf(Team::class)
        ->and($registry->findByTable('countries'))->toBeInstanceOf(Country::class)
        ->and($registry->findByTable('unknown'))->toBeNull();
    config()->set('filament-odk-link.models.registry', [Team::class, SameTeamTable::class]);
    expect(fn () => $registry->classes())->toThrow(InvalidArgumentException::class, 'duplicate')
        ->and(fn () => $registry->findByTable('unknown'))->toThrow(InvalidArgumentException::class, 'duplicate');
});

it('rejects invalid registry entries', function ($class) {
    config()->set('filament-odk-link.models.registry', [$class]);
    expect(fn () => app(ConfiguredModelRegistry::class)->classes())->toThrow(InvalidArgumentException::class, 'models.registry');
})->with([stdClass::class, RegistryNeedsArgument::class, 'NoSuchModel']);
