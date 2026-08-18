<?php

namespace PhpCliChat\Protocol\Message;

use PhpCliChat\Protocol\Codec\MalformedMessage;
use PhpCliChat\Protocol\Message;

readonly class Broadcast implements Message
{
    public function __construct(
        public int    $from,
        public string $text,
    ) {}

    public function type(): string
    {
        return 'chat';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return ['from' => $this->from, 'text' => $this->text];
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws MalformedMessage
     */
    public static function fromPayload(array $payload): self
    {
        $from = $payload['from'] ?? null;
        $text = $payload['text'] ?? null;

        if (!is_int($from)) {
            throw new MalformedMessage('chat: "from" must be an int');
        }

        if (!is_string($text)) {
            throw new MalformedMessage('chat: "text" must be a string');
        }

        return new self($from, $text);
    }
}
