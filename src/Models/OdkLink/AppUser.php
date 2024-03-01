<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use JsonException;

class AppUser extends Model
{
    protected $table = 'app_users';



    public function odkProject(): BelongsTo
    {
        return $this->belongsTo(OdkProject::class);
    }

    public function xlsforms(): BelongsToMany
    {
        return $this->belongsToMany(Xlsform::class, 'app_user_assignments');
    }

    /**
     * Method to retrieve the encoded settings to create a QR code that allows access to the entire project.
     *
     * @throws JsonException
     */
    public function getQrCodeStringAttribute(): ?string
    {
        // Question: After scanning QR code, it does not show the published ODK form.
        // It is different from scanning QR code in ODK central...
        $settings = [
            "general" => [
                "server_url" => config('filament-odk-link.odk.base_endpoint') . "/key/{$this->token}/projects/{$this->odkProject->id}",
                "form_update_mode" => "match_exactly",
            ],
            "project" => ["name" => $this->odkProject->name],
            "admin" => ["automatic_update" => true],
        ];

        $json = json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return base64_encode(
            zlib_encode(
                $json,
                ZLIB_ENCODING_DEFLATE
            )
        );
    }
}
