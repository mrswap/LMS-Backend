<?php

namespace App\Events;

use App\Models\SupportMessage;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $message;

    public function __construct(SupportMessage $message)
    {
        $this->message = $message->load([
            'sender',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CHANNEL
    |--------------------------------------------------------------------------
    */

    public function broadcastOn(): array
    {
        return [

            new PrivateChannel(
                'support.thread.' . $this->message->thread_id
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | EVENT NAME
    |--------------------------------------------------------------------------
    */

    public function broadcastAs(): string
    {
        return 'support.message.sent';
    }

    /*
    |--------------------------------------------------------------------------
    | PAYLOAD
    |--------------------------------------------------------------------------
    */

    public function broadcastWith(): array
    {
        return [

            'message' => $this->message,
        ];
    }
}
