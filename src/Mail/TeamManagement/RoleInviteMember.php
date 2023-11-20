<?php

namespace Stats4sd\FilamentOdkLink\Mail\TeamManagement;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Stats4sd\FilamentOdkLink\Models\TeamManagement\RoleInvite;

class RoleInviteMember extends Mailable
{
    use Queueable;
    use SerializesModels;

    public RoleInvite $invite;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(RoleInvite $invite)
    {
        $this->invite = $invite;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this->from(config('mail.from.address'))
            ->subject(config('app.name') . ': Invitation To Join with the  ' . $this->invite->role->name . ' User Role')
            ->markdown('team-management::emails.role_invite');
    }
}
