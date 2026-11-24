@php
    $requiredMediaCount = $getRecord()->requiredFixedMedia()->count();
    $attachedMediaCount = $getRecord()->attachedFixedMedia()->count();
@endphp

<div class="text-xs fi-ta-text grid gap-y-1 px-3 py-4 {{$attachedMediaCount === $requiredMediaCount ? 'text-success-600' : 'text-danger-600'}}">
    {{ $attachedMediaCount }} / {{ $requiredMediaCount }}
</div>
