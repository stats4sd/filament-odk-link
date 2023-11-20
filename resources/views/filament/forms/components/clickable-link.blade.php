<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div x-data="{ state: $wire.$entangle('{{ $getStatePath() }}') }">
        <a target="_blank" href="{{ $getRecord()->enketo_draft_url }}">{{ $getRecord()->enketo_draft_url }}</a>
    </div>
</x-dynamic-component>
