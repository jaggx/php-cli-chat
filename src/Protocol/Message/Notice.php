<?php

namespace PhpCliChat\Protocol\Message;

use PhpCliChat\Protocol\Codec\MalformedMessage;
use PhpCliChat\Protocol\Message;

readonly class Notice implements Message
{
    public function __construct(
        public string $text,
    ) {}

    public function type(): string
    {
        return 'notice';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return ['text' => $this->text];
    }

    /**
     * @param array<array-key, mixed> $payload
     * @throws MalformedMessage
     */
    public static function fromPayload(array $payload): self
    {
        $text = $payload['text'] ?? null;

        if (!is_string($text)) {
            throw new MalformedMessage('notice: "text" must be a string');
        }

        return new self($text);
    }
}
