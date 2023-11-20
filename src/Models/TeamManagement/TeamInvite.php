<?php

namespace Stats4sd\FilamentOdkLink\Models\TeamManagement;

use Stats4sd\FilamentOdkLink\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamInvite extends Model
{
    protected $table = 'team_invites';
    protected $guarded = [];
    protected $casts = [
        'is_confirmed' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('unconfirmed', function (Builder $builder) {
            $builder->where('is_confirmed', false);
        });
    }

    // *********** RELATIONSHIPS ************ //
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // ************ METHODS ************ //

    public function confirm(): bool
    {
        $this->is_confirmed = 1;
        $this->save();

        return $this->is_confirmed;
    }
}
