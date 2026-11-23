<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div>
        {{ QrCode::size(150)->generate($getRecord()->draftQrCodeString) }}
    </div>
</x-dynamic-component>
