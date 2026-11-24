<?php

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Import;
use Stats4sd\OdkLink\Core\Tests\Architecture\ShippingBoundary;

it('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

it('marks every export class as a Laravel Excel export')
    ->expect('Stats4sd\OdkLink\Core\Exports')
    ->classes()
    ->toImplement(Export::class);

it('marks every import class as a Laravel Excel import')
    ->expect('Stats4sd\OdkLink\Core\Imports')
    ->classes()
    ->toImplement(Import::class);

it('keeps every core namespace independent of host and UI dependencies')
    ->expect('Stats4sd\OdkLink\Core')
    ->not->toUse(['App', 'Filament', 'Livewire', 'Spatie\Permission', 'Stats4sd\FilamentTeamManagement', 'HaydenPierce\ClassFinder', 'Stats4sd\OdkLink\Filament', 'Stats4sd\FilamentOdkLink']);

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

    // The step 6 class-name migration must name the pre-rename prefix to rewrite it.
    $violations = array_values(array_diff($violations, [
        'database/migrations/035_rename_odk_link_class_names.php: Stats4sd\\FilamentOdkLink\\',
    ]));

    expect($violations)->toBe([]);
});

it('recognizes aliases fully qualified calls and executable strings while ignoring comments', function () {
    foreach ([
        '<?php use Filament\\Facades\\Filament as Panel;',
        '<?php \\Livewire\\Livewire::test();',
        '<?php return ["model" => \'App\\\\Models\\\\Team\'];',
        '<?php return \'Stats4sd\\\\OdkLink\\\\Core\\\\Tests\\\\Models\\\\Team\';',
        '<?php return \'Super Admin\';',
        '<?php use Stats4sd\\FilamentTeamManagement\\Models\\User;',
        '<?php $class::$method($submission);',
    ] as $source) {
        expect(ShippingBoundary::violations($source))->not->toBeEmpty();
    }

    expect(ShippingBoundary::violations('<?php // use App\\Models\\Team;' . "\n" . '/** Filament\\Facades\\Filament */'))->toBe([]);
});
