@php

// find the team datasets required from the xlsformtemplate
$datasets = $getRecord()->xlsformTemplate->requiredDataMedia->where('dataset_id', '!=', null)->pluck('dataset_id')->unique();

@endphp

<div class="p-4">
    {{ $datasets->count() }}
</div>
