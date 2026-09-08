<?php

namespace Stats4sd\FilamentOdkLink\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class XlsformModuleVersionWasImported implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public int $xlsformModuleId) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('xlsforms'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'FilamentOdkLink.XlsformModuleVersionWasImported';
    }
}
