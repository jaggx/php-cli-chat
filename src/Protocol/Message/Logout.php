<?php

namespace PhpCliChat\Protocol\Message;

use PhpCliChat\Protocol\Message;

readonly class Logout implements Message
{
    public function type(): string
    {
        return 'logout';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [];
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self();
    }
}
