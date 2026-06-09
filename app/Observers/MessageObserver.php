<?php

namespace App\Observers;

use App\Models\Conversation;
use App\Models\Message;

class MessageObserver
{
    /**
     * Stamp the conversation's first-response time the first time an agent sends
     * an outbound message. This is the reliable signal for response-time metrics
     * (there's no dedicated "reply" action). Bot/system/customer messages don't count.
     */
    public function created(Message $message): void
    {
        if ($message->direction !== 'out' || $message->author !== 'agent') {
            return;
        }

        $conversation = Conversation::find($message->conversation_id);

        if ($conversation && $conversation->first_response_at === null) {
            $conversation->forceFill(['first_response_at' => $message->sent_at])->saveQuietly();
        }
    }
}
