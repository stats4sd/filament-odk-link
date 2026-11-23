<?php

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Import;
use Stats4sd\FilamentOdkLink\Tests\Architecture\ShippingBoundary;

it('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

it('marks every export class as a Laravel Excel export')
    ->expect('Stats4sd\FilamentOdkLink\Exports')
    ->classes()
    ->toImplement(Export::class);

it('marks every import class as a Laravel Excel import')
    ->expect('Stats4sd\FilamentOdkLink\Imports')
    ->classes()
    ->toImplement(Import::class);

it('keeps every core namespace independent of host and UI dependencies')
    ->expect('Stats4sd\FilamentOdkLink')
    ->not->toUse(['App', 'Filament', 'Livewire', 'Spatie\Permission', 'Stats4sd\FilamentTeamManagement', 'HaydenPierce\ClassFinder', 'Stats4sd\FilamentOdkLink\Filament', 'Stats4sd\FilamentOdkLink\Forms', 'Stats4sd\FilamentOdkLink\Testing']);

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

            foreach (ShippingBoundary::violations(file_get_contents($file->getPathname())) as $violation) {
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
        '<?php use Stats4sd\\FilamentTeamManagement\\Models\\User;',
        '<?php $class::$method($submission);',
    ] as $source) {
        expect(ShippingBoundary::violations($source))->not->toBeEmpty();
    }

    expect(ShippingBoundary::violations('<?php // use App\\Models\\Team;' . "\n" . '/** Filament\\Facades\\Filament */'))->toBe([]);
});
