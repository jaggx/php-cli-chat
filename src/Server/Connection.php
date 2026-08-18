<?php

namespace PhpCliChat\Server;

use Amp\Socket;
use PhpCliChat\Protocol\Message;
use PhpCliChat\Protocol\MessageChannel;
use PhpCliChat\Protocol\Transport\LineStream;
use PhpCliChat\Protocol\Unreadable;

readonly class Connection
{
    public function __construct(
        public int             $id,
        private MessageChannel $channel,
    ) {}

    public static function accept(Socket\Socket $socket, int $id): self
    {
        return new self($id, MessageChannel::forServer(new LineStream($socket)));
    }

    public function getRemoteAddress(): Socket\SocketAddress
    {
        return $this->channel->getRemoteAddress();
    }

    public function send(Message $message): void
    {
        $this->channel->send($message);
    }

    /**
     * @return \Traversable<int, Message|Unreadable>
     */
    public function receive(): \Traversable
    {
        return $this->channel->receive();
    }

    public function close(): void
    {
        $this->channel->close();
    }
}
