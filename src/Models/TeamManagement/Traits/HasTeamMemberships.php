<?php

namespace Stats4sd\FilamentOdkLink\Models\TeamManagement\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stats4sd\FilamentOdkLink\Models\TeamManagement\RoleInvite;
use Stats4sd\FilamentOdkLink\Models\TeamManagement\Team;
use Stats4sd\FilamentOdkLink\Models\TeamManagement\TeamInvite;

/**
 * Add this trait to any class that can be a member of a team.
 *
 * Typically, this will be your Stats4sd\FilamentOdkLink\Models\User class, but it could in theory be anythng;
 */
trait HasTeamMemberships
{
    protected static function booted()
    {
        parent::booted();

        // handle newly created 'users' when they are created via an invite link
        static::created(function ($member) {

            // if the user was invited to one or more teams, assign them to the team(s)
            $invites = TeamInvite::where('email', '=', $member->email)->get();

            foreach ($invites as $invite) {
                $member->teams()->syncWithoutDetaching($invite->team->id);
                $invite->confirm();
            }

            // if the user was invited to one or more user roles, assign them to the role(s)
            $roleInvites = RoleInvite::where('email', '=', $member->email)->get();

            foreach ($roleInvites as $invite) {
                $member->roles()->syncWithoutDetaching($invite->role->id);
                $invite->confirm();
            }
        });
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')->withPivot('is_admin');
    }

    public function teamInvitesSent(): HasMany
    {
        return $this->hasMany(TeamInvite::class, 'inviter_id');
    }

    public function roleInvitesSent(): HasMany
    {
        return $this->hasMany(RoleInvite::class, 'inviter_id');
    }
}
