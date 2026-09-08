<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class RequiredMedia extends Pivot implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'required_media';

    protected $casts = [
        'is_static' => 'boolean',
        'links_to_dataset' => 'boolean',
    ];

    protected $guarded = [];

    protected static function booted(): void
    {

        // when deleting, also delete any attached media;
        static::deleting(static function ($requiredMedia) {
            $requiredMedia->getMedia()
                ->each(fn ($media) => $requiredMedia->deleteMedia($media));
        });

        // when updating, update the related xlsform template to set draft_needs_update to true (to ensure the updated media is pushed to ODK Central for testing
        static::saved(static function (RequiredMedia $requiredMedia) {
            $requiredMedia->xlsformTemplate->update(
                ['draft_needs_update' => true]
            );

        });
    }

    /** @return Attribute<int, never> */
    protected function status(): Attribute
    {
        return new Attribute(
            get: fn (): int => $this->dataset_id || $this->choice_list_id || $this->hasMedia() ? 1 : 0,
        );
    }

    /** @return Attribute<string, never> */
    protected function fullType(): Attribute
    {
        return new Attribute(
            get: function (): string {

                // 3 possible types
                // - a static file (return the 'type' prop from ODK
                // - a csv created dynamically from a dataset (where the dataset_id is not null)
                // - a csv created dynamically from a choice list (where the choice_list_id is not null)

                if ($this->is_static) {
                    return $this->type;
                }

                if ($this->dataset_id) {
                    return 'dataset';
                }

                if ($this->choice_list_id) {
                    return 'choice_list';
                }

                return 'unlinked';
            }
        );
    }

    /** @return BelongsTo<XlsformTemplate, $this> */
    public function xlsformTemplate(): BelongsTo
    {
        return $this->belongsTo(XlsformTemplate::class);
    }

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    // *** ONLY HOLPA FOR NOW ***
    /** @return BelongsTo<ChoiceList, $this> */
    public function choiceList(): BelongsTo
    {
        return $this->belongsTo(ChoiceList::class);
    }

    // maybe need to get imageUrl (for media attachments) and/or dataset attachment...

}
