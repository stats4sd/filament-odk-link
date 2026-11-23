<?php

use Stats4sd\FilamentOdkLink\Filament\Tests\Architecture\ShippingBoundary;

it('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

it('keeps the UI independent of host apps and role packages', function () {
    $root = dirname(__DIR__);
    $violations = [];

    foreach (['src', 'resources/views'] as $directory) {
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
