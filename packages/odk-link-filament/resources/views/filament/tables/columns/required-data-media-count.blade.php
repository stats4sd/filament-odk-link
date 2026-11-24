@php
    $requiredMediaCount = $getRecord()->requiredDataMedia()->count();
    $attachedMediaCount = $getRecord()->attachedDataMedia()->count();
@endphp

<div class="fi-ta-text grid gap-y-1 px-3 py-4 text-xs text-center {{$attachedMediaCount === $requiredMediaCount ? 'text-success-600' : 'text-danger-600'}}">
    {{ $attachedMediaCount }} / {{ $requiredMediaCount }}
</div>
