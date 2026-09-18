<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Stats4sd\FilamentOdkLink\Contracts\CurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Contracts\OperationNotifier;
use Stats4sd\FilamentOdkLink\Contracts\RoleResolver;
use Stats4sd\FilamentOdkLink\Contracts\SubmissionProcessor;
use Stats4sd\FilamentOdkLink\FilamentOdkLinkServiceProvider;
use Stats4sd\FilamentOdkLink\OdkLinkCoreServiceProvider;
use Stats4sd\FilamentOdkLink\Support\ConfiguredModels;
use Stats4sd\FilamentOdkLink\Support\EmptyRoleResolver;
use Stats4sd\FilamentOdkLink\Support\NullCurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Support\NullOperationNotifier;
use Stats4sd\FilamentOdkLink\Support\NullSubmissionProcessor;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;
use Symfony\Component\Process\Process;

class Organisation extends Team
{
    protected $table = 'organisations';
}

it('boots core without UI or role providers and resolves disabled integration defaults', function () {
    $providers = array_keys(app()->getLoadedProviders());
    expect($providers)->not->toContain(FilamentOdkLinkServiceProvider::class);
    foreach ($providers as $provider) {
        expect($provider)->not->toStartWith('Filament\\')->not->toStartWith('Livewire\\')->not->toStartWith('Spatie\\Permission\\');
    }
    expect(app(CurrentOwnerResolver::class))->toBeInstanceOf(NullCurrentOwnerResolver::class)
        ->and(app(CurrentOwnerResolver::class)->current())->toBeNull()
        ->and(app(OperationNotifier::class))->toBeInstanceOf(NullOperationNotifier::class)
        ->and(app(RoleResolver::class))->toBeInstanceOf(EmptyRoleResolver::class)
        ->and(app(SubmissionProcessor::class))->toBeInstanceOf(NullSubmissionProcessor::class)
        ->and(Schema::hasTable('roles'))->toBeFalse();
});

it('defers required model validation until the configured class is used', function () {
    config()->set('filament-odk-link.models.form_owner', null);
    config()->set('filament-odk-link.models.user_model', null);
    expect(app(CurrentOwnerResolver::class)->current())->toBeNull()
        ->and(app(OperationNotifier::class))->toBeInstanceOf(NullOperationNotifier::class);
    expect(fn () => app(ConfiguredModels::class)->formOwnerClass())->toThrow(InvalidArgumentException::class, 'models.form_owner');
    expect(fn () => app(ConfiguredModels::class)->userClass())->toThrow(InvalidArgumentException::class, 'models.user_model');
});

it('creates foreign keys against an alternate configured owner table', function () {
    config()->set('filament-odk-link.models.form_owner', Organisation::class);
    Schema::create('organisations', function (Blueprint $table) {
        $table->id();
    });
    Schema::drop('xlsforms');
    (require __DIR__ . '/../../database/migrations/002_create_xlsforms_table.php')->up();
    $ownerKey = collect(Schema::getForeignKeys('xlsforms'))->first(fn (array $key) => $key['columns'] === ['owner_id']);
    expect($ownerKey['foreign_table'])->toBe('organisations');
});

it('caches package configuration in a disposable core host before models are configured', function () {
    $directory = sys_get_temp_dir() . '/odk-headless-' . bin2hex(random_bytes(6));
    foreach (['bootstrap/cache', 'config', 'storage/framework/cache', 'storage/logs'] as $path) {
        mkdir($directory . '/' . $path, 0777, true);
    }
    $autoload = realpath(__DIR__ . '/../../vendor/autoload.php');
    $provider = OdkLinkCoreServiceProvider::class;
    file_put_contents($directory . '/bootstrap/app.php', '<?php return Illuminate\\Foundation\\Application::configure(basePath: ' . var_export($directory, true) . ')->withProviders([' . var_export($provider, true) . '])->create();');
    file_put_contents($directory . '/artisan', '<?php require ' . var_export($autoload, true) . '; $app = require __DIR__ . "/bootstrap/app.php"; exit($app->handleCommand(new Symfony\\Component\\Console\\Input\\ArgvInput));');

    try {
        $process = new Process([PHP_BINARY, $directory . '/artisan', 'config:cache'], $directory, ['APP_CONFIG_CACHE' => $directory . '/bootstrap/cache/config.php']);
        $process->mustRun();
        $cached = require $directory . '/bootstrap/cache/config.php';
        expect($cached['filament-odk-link']['models']['form_owner'])->toBeNull()
            ->and($cached['filament-odk-link']['contracts']['submission_processor'])->toBe(NullSubmissionProcessor::class);
        $process->mustRun();
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
});
