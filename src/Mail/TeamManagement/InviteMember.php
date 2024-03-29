<?php

namespace Stats4sd\FilamentOdkLink\Mail\TeamManagement;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Stats4sd\FilamentOdkLink\Models\TeamManagement\TeamInvite;

class InviteMember extends Mailable
{
    use Queueable;
    use SerializesModels;

    public TeamInvite $invite;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(TeamInvite $invite)
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
            ->subject(config('app.name') . ': Invitation To Join Team ' . $this->invite->team->name)
            ->markdown('filament-odk-link::emails.team_invite');
    }
}
