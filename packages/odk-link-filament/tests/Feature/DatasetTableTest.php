<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Stats4sd\OdkLink\Core\Models\OdkLink\Dataset;
use Stats4sd\OdkLink\Filament\OdkAdmin\Resources\Datasets\DatasetResource;
use Stats4sd\OdkLink\Filament\OdkAdmin\Resources\Datasets\Pages\ListDatasets;

beforeEach(function () {
    Filament::setCurrentPanel('odk');
});

it('links dataset rows to the view page of the panel the plugin is registered on', function () {
    $dataset = Dataset::create(['name' => 'households']);

    $url = DatasetResource::getUrl('view', ['record' => $dataset]);

    expect($url)->toContain('/odk/');

    Livewire::test(ListDatasets::class)
        ->assertSuccessful()
        ->assertSee($url, escape: false);
});
