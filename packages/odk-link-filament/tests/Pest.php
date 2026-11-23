<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Stats4sd\OdkLink\Filament\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Unit', 'Smoke', 'ArchTest.php');
