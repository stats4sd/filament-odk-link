<?php

use Stats4sd\FilamentOdkLink\Tests\Architecture\ShippingBoundary;

it('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

it('keeps domain namespaces independent of host and UI dependencies')
    ->expect([
        'Stats4sd\FilamentOdkLink\Contracts',
        'Stats4sd\FilamentOdkLink\Support',
        'Stats4sd\FilamentOdkLink\Models',
        'Stats4sd\FilamentOdkLink\Jobs',
        'Stats4sd\FilamentOdkLink\Services',
        'Stats4sd\FilamentOdkLink\Concerns',
        'Stats4sd\FilamentOdkLink\Commands',
        'Stats4sd\FilamentOdkLink\Exports',
        'Stats4sd\FilamentOdkLink\Imports',
        'Stats4sd\FilamentOdkLink\Listeners',
        'Stats4sd\FilamentOdkLink\OdkLinkCoreServiceProvider',
    ])
    ->not->toUse(['App', 'Filament', 'Livewire', 'Spatie\Permission', 'HaydenPierce\ClassFinder', 'Stats4sd\FilamentOdkLink\Filament', 'Stats4sd\FilamentOdkLink\Forms', 'Stats4sd\FilamentOdkLink\Testing']);

it('enforces the boundary across every shipping PHP file including executable class strings', function () {
    $root = dirname(__DIR__);
    $violations = [];

    foreach (['src', 'database', 'config', 'routes'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$directory}"));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1);
            $ui = str_starts_with($relative, 'src/Filament/')
                || str_starts_with($relative, 'src/Forms/')
                || $relative === 'src/Testing/TestsFilamentOdkLink.php'
                || $relative === 'src/OdkLinkAdmin.php'
                || $relative === 'src/FilamentOdkLinkServiceProvider.php';

            foreach (ShippingBoundary::violations(file_get_contents($file->getPathname()), $ui) as $violation) {
                $violations[] = "{$relative}: {$violation}";
            }
        }
    }

    expect($violations)->toBe([]);
});

it('recognizes aliases fully qualified calls and executable strings while ignoring comments', function () {
    foreach ([
        '<?php use Filament\\Facades\\Filament as Panel;',
        '<?php \\Livewire\\Livewire::test();',
        '<?php return ["model" => \'App\\\\Models\\\\Team\'];',
        '<?php return \'Stats4sd\\\\FilamentOdkLink\\\\Tests\\\\Models\\\\Team\';',
        '<?php return \'Super Admin\';',
        '<?php $class::$method($submission);',
    ] as $source) {
        expect(ShippingBoundary::violations($source, false))->not->toBeEmpty();
    }

    expect(ShippingBoundary::violations('<?php // use App\\Models\\Team;' . "\n" . '/** Filament\\Facades\\Filament */', false))->toBe([]);
});
