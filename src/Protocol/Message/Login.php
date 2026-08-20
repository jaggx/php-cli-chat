<?php

namespace PhpCliChat\Protocol\Message;

use PhpCliChat\Protocol\Codec\MalformedMessage;
use PhpCliChat\Protocol\Message;

readonly class Login implements Message
{
    public function __construct(
        public string $name,
    ) {}

    public function type(): string
    {
        return 'login';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return ['name' => $this->name];
    }

    /**
     * @param array<array-key, mixed> $payload
     * @throws MalformedMessage
     */
    public static function fromPayload(array $payload): self
    {
        $name = $payload['name'] ?? null;

        if (!is_string($name)) {
            throw new MalformedMessage('login: "name" must be a string');
        }

        return new self($name);
    }
}
