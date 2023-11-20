<div class="p-4">
    {{ QrCode::size(150)->generate($getRecord()->odkProject->appUsers->first()->qr_code_string) }}
        <h5>SCAN QR Code in ODK Collect</h5>
</div>
