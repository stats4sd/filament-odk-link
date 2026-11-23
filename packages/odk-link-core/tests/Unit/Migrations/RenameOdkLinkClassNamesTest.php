<?php

use Illuminate\Support\Facades\DB;
use Stats4sd\OdkLink\Core\Models\OdkLink\OdkProject;
use Stats4sd\OdkLink\Core\Models\OdkLink\Platform;
use Stats4sd\OdkLink\Core\Tests\Models\Team;

function renameClassNamesMigration(): object
{
    return require __DIR__ . '/../../../database/migrations/035_rename_odk_link_class_names.php';
}

it('rewrites pre-rename core class names and leaves host class names alone', function () {
    DB::table('odk_projects')->insert([
        ['id' => 1, 'owner_type' => 'Stats4sd\\FilamentOdkLink\\Models\\OdkLink\\Platform', 'owner_id' => 1, 'name' => 'platform'],
        ['id' => 2, 'owner_type' => Team::class, 'owner_id' => 1, 'name' => 'team'],
    ]);
    DB::table('datasets')->insert([
        'name' => 'countries',
        'model_type' => 'Stats4sd\\FilamentOdkLink\\Models\\Country',
    ]);

    renameClassNamesMigration()->up();

    expect(DB::table('odk_projects')->orderBy('id')->pluck('owner_type')->all())
        ->toBe([Platform::class, Team::class])
        ->and(DB::table('datasets')->value('model_type'))->toBe('Stats4sd\\OdkLink\\Core\\Models\\Country')
        ->and(OdkProject::find(1)->owner()->getRelated())->toBeInstanceOf(Platform::class);
});

it('reverses the rewrite on down', function () {
    DB::table('odk_projects')->insert([
        'id' => 1, 'owner_type' => Platform::class, 'owner_id' => 1, 'name' => 'platform',
    ]);

    renameClassNamesMigration()->down();

    expect(DB::table('odk_projects')->value('owner_type'))
        ->toBe('Stats4sd\\FilamentOdkLink\\Models\\OdkLink\\Platform');
});

it('is a no-op on a fresh database', function () {
    renameClassNamesMigration()->up();

    expect(DB::table('odk_projects')->count())->toBe(0);
});
