@if($getRecord()->odkProject)
<div class="p-4">
    {{ QrCode::size(150)->generate($getRecord()->odkProject->appUsers->first()->qr_code_string) }}
        <h5>SCAN QR Code in ODK Collect</h5>
</div>
    @else
    <div class="p-4">
        <h5 class="text-center text-danger-600">ODK Project not found. Is this platform connected to a value ODK Central server?</h5>
    </div>
    @endif
