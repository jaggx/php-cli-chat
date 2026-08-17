<?php

namespace PhpCliChat\Server;

use Amp\Socket;
use PhpCliChat\Protocol\MessageStream;

readonly class Connection
{
    public function __construct(
        public int            $id,
        private MessageStream $stream,
    ) {}

    public function getRemoteAddress(): Socket\SocketAddress
    {
        return $this->stream->getRemoteAddress();
    }

    public function send(string $message): void
    {
        $this->stream->send($message);
    }

    /**
     * @return \Traversable<int, string>
     */
    public function receive(): \Traversable
    {
        return $this->stream->receive();
    }

    public function close(): void
    {
        $this->stream->close();
    }
}
