<?php

use Stats4sd\FilamentOdkLink\Models\Country;
use Stats4sd\FilamentOdkLink\Services\HelperService;

// ─── getOdkVariablesToIgnore ──────────────────────────────────────────────────

it('getOdkVariablesToIgnore returns all expected system fields', function () {
    $vars = HelperService::getOdkVariablesToIgnore();

    expect($vars)
        ->toBeArray()
        ->toContain('__id')
        ->toContain('instanceID')
        ->toContain('meta')
        ->toContain('deviceid')
        ->toContain('_uuid')
        ->toContain('_attachments')
        ->toContain('_submission_time')
        ->toContain('_submitted_by');
});

// ─── importCsvFileToCollection ────────────────────────────────────────────────

it('importCsvFileToCollection parses rows keyed by header', function () {
    $path = __DIR__ . '/../../fixtures/lookup.csv';

    $rows = HelperService::importCsvFileToCollection($path);

    expect($rows)->toHaveCount(2);
    expect($rows->first()->get('name'))->toBe('alpha');
    expect($rows->first()->get('label'))->toBe('Alpha Village');
    expect($rows->first()->get('region'))->toBe('north');
});

it('importCsvFileToCollection maps all rows to correct values', function () {
    $path = __DIR__ . '/../../fixtures/lookup.csv';

    $rows = HelperService::importCsvFileToCollection($path);
    $second = $rows->last();

    expect($second->get('name'))->toBe('beta');
    expect($second->get('label'))->toBe('Beta Town');
    expect($second->get('region'))->toBe('south');
});

it('importCsvFileToCollection strips trailing blank lines', function () {
    $path = __DIR__ . '/../../fixtures/lookup.csv';

    // The fixture has a trailing newline; the result should only have 2 data rows.
    $rows = HelperService::importCsvFileToCollection($path);

    expect($rows)->toHaveCount(2);
});

// ─── getModelByTablename ──────────────────────────────────────────────────────

// getModelByTablename scans Stats4sd\FilamentOdkLink\Models (top-level only — ClassFinder
// does not recurse into OdkLink subnamespace). Country is a top-level model.
it('getModelByTablename resolves the countries table to the Country model', function () {
    $model = HelperService::getModelByTablename('countries');

    expect($model)->toBeInstanceOf(Country::class);
});

it('getModelByTablename returns null for an unknown table name', function () {
    $result = HelperService::getModelByTablename('nonexistent_table_xyz_999');

    expect($result)->toBeNull();
});
