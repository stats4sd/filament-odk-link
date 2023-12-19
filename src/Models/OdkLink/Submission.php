<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submission extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'submissions';

    protected $guarded = ['id'];

    protected $casts = [
        'content' => 'array',
        'errors' => 'array',
        'entries' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('owned', static function (Builder $query) {
            if (Auth::check() && ! Auth::user()?->hasRole(config('filament-odk-link.roles.xlsform-admin'))) {
                $query->where(function (Builder $query) {
                    $query->whereHas('xlsformVersion', function (Builder $query) {
                        $query->whereHas('xlsform', function (Builder $query) {
                            $query->whereHas('owner', function (Builder $query) {

                                //if xlsforms are owned by a user, return the user's forms directly.
                                if (is_a($query->getModel(), User::class)) {
                                    $query->where('users.id', Auth::id());
                                } else {
                                    // is the xlsform owned by a team/group that the logged-in user is linked to?
                                    $query->whereHas('users', function ($query) {
                                        $query->where('users.id', Auth::id());
                                    });
                                }
                            });
                        });
                    });
                });
            }
        });
    }

    // $this->entries is an array of every Model entry created as a result of processing this submission.
    // This helper function makes it easy to update this array.
    public function addEntry(string $model, array $ids): void
    {
        $value = $this->entries;

        if ($value && array_key_exists($model, $value)) {
            $value[$model] = array_merge($value[$model], $ids);
        } else {
            $value[$model] = $ids;
        }

        $this->entries = $value;
        $this->save();
    }

    public function xlsformVersion(): BelongsTo
    {
        return $this->belongsTo(XlsformVersion::class);
    }

    public function xlsformTitle(): Attribute
    {
        return new Attribute(
            get: fn () => $this->xlsformVersion->xlsform->title,
        );
    }

    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class);
    }

}
