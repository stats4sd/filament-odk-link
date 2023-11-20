<?php

namespace Stats4sd\FilamentOdkLink\Models\TeamManagement;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stats4sd\FilamentOdkLink\Models\User;

class RoleInvite extends Model
{
    protected $table = 'role_invites';

    protected $guarded = [];

    protected $casts = [
        'is_confirmed' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('unconfirmed', static function (Builder $builder) {
            $builder->where('is_confirmed', false);
        });
    }

    // *********** RELATIONSHIPS ************ //
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
