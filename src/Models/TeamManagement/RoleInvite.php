<?php

namespace Stats4sd\FilamentOdkLink\Models\TeamManagement;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Models\User;

class RoleInvite extends Model
{
    protected $table = 'role_invites';

    protected $guarded = [];

    protected $casts = [
        'is_confirmed' => 'boolean',
    ];

    protected static function booted()
    {
        static::addGlobalScope('unconfirmed', function (Builder $builder) {
            $builder->where('is_confirmed', false);
        });
    }

    // *********** RELATIONSHIPS ************ //
    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
