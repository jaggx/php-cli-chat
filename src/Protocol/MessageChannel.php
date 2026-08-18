<?php

namespace PhpCliChat\Protocol;

use Amp\Socket;
use PhpCliChat\Protocol\Codec\Decoder;
use PhpCliChat\Protocol\Codec\Encoder;
use PhpCliChat\Protocol\Codec\MalformedMessage;
use PhpCliChat\Protocol\Transport\LineStream;

readonly class MessageChannel
{
    public function __construct(
        private LineStream $stream,
        private Decoder    $decoder,
    ) {}

    public static function forServer(LineStream $stream): self
    {
        return new self($stream, Decoder::forServer());
    }

    public static function forClient(LineStream $stream): self
    {
        return new self($stream, Decoder::forClient());
    }

    public function getRemoteAddress(): Socket\SocketAddress
    {
        return $this->stream->getRemoteAddress();
    }

    public function send(Message $message): void
    {
        $this->stream->send(Encoder::encode($message));
    }

    /**
     * @return \Traversable<int, Message|Unreadable>
     */
    public function receive(): \Traversable
    {
        foreach ($this->stream->receive() as $line) {
            if ('' === $line) {
                continue; // a framing artifact, not a protocol error
            }

            try {
                yield $this->decoder->decode($line);
            } catch (MalformedMessage $e) {
                yield new Unreadable($e->getMessage());
            }
        }
    }

    public function close(): void
    {
        $this->stream->close();
    }
}
