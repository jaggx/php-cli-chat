<?php

namespace Tests\Support;

use PhpCliChat\Protocol\Message;
use PhpCliChat\Protocol\MessageChannel;
use PhpCliChat\Protocol\Unreadable;

use function Amp\async;

/**
 * The fiber owns the only read on the channel, so a test cannot iterate it
 * itself without racing.
 */
class MessageCollector
{
    /**
     * @var list<Message|Unreadable>
     */
    public array $messages = [];

    /**
     * True once the channel's iteration ended, i.e. the peer hung up.
     */
    public bool $closed = false;

    public function __construct(MessageChannel $channel)
    {
        async(function () use ($channel) {
            foreach ($channel->receive() as $message) {
                $this->messages[] = $message;
            }

            $this->closed = true;
        })->ignore();
    }
}
