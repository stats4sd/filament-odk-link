<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div x-data="{ state: $wire.$entangle('{{ $getStatePath() }}') }">
        {{ QrCode::size(150)->generate($getRecord()->draftQrCodeString) }}
    </div>
</x-dynamic-component>
