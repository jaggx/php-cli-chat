<?php

namespace PhpCliChat\Protocol;

use PhpCliChat\Protocol\Codec\MalformedMessage;

interface Message
{
    public function type(): string;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array;

    /**
     * @param array<array-key, mixed> $payload
     * @throws MalformedMessage
     */
    public static function fromPayload(array $payload): self;
}
