<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Stats4sd\FilamentOdkLink\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

// Run package + test migrations for every test that touches the database.
// ArchTest and other pure tests are unaffected (no DB queries = no migration cost beyond setup).
uses(RefreshDatabase::class)->in(__DIR__);
