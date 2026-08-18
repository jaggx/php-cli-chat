<?php

namespace PhpCliChat\Protocol\Transport;

use Amp\Socket;

use function Amp\async;
use function Amp\ByteStream\splitLines;

readonly class LineStream
{
    public function __construct(
        private Socket\Socket $socket,
    ) {}

    public static function connect(string $address): self
    {
        return new self(Socket\connect($address));
    }

    public function getRemoteAddress(): Socket\SocketAddress
    {
        return $this->socket->getRemoteAddress();
    }

    public function send(string $line): void
    {
        async(fn() => $this->socket->write($line . PHP_EOL))->ignore();
    }

    /**
     * @return \Traversable<int, string>
     */
    public function receive(): \Traversable
    {
        return splitLines($this->socket);
    }

    public function close(): void
    {
        $this->socket->close();
    }
}
