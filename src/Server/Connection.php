<?php

namespace PhpCliChat\Server;

use Amp\ByteStream\WritableStream;
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

    /**
     * @param Socket\Socket $socket
     * @param int $id
     * @param ?WritableStream $debug
     * @return Connection
     */
    public static function accept(Socket\Socket $socket, int $id, ?WritableStream $debug = null): self
    {
        $log = null === $debug ? null : new ConnectionLog($debug, $id);

        return new self($id, MessageChannel::forServer(new LineStream($socket), $log));
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
