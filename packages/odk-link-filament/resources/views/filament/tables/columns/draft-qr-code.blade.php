<div class="p-8">
    @if($getRecord()->draft_qr_code_string)
        {{ QrCode::size(100)->generate($getRecord()->draft_qr_code_string) }}
    @else
        <p>No QR Code</p>
    @endif
</div>
