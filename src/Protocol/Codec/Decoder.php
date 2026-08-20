<?php

namespace PhpCliChat\Protocol\Codec;

use PhpCliChat\Protocol\Message;

readonly class Decoder
{
    /**
     * @param array<string, class-string<Message>> $registry
     */
    private function __construct(
        private array $registry,
    ) {}

    public static function toServer(): self
    {
        return new self([
            'chat' => Message\Chat::class,
            'login' => Message\Login::class,
        ]);
    }

    public static function toClient(): self
    {
        return new self([
            'chat' => Message\Broadcast::class,
            'notice' => Message\Notice::class,
        ]);
    }

    /**
     * @throws MalformedMessage
     */
    public function decode(string $line): Message
    {
        try {
            $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new MalformedMessage("invalid JSON: {$e->getMessage()}", previous: $e);
        }

        if (!is_array($decoded)) {
            throw new MalformedMessage('expected a JSON object, got ' . get_debug_type($decoded));
        }

        $type = $decoded['type'] ?? null;

        if (!is_string($type)) {
            throw new MalformedMessage('missing or non-string "type"');
        }

        $class = $this->registry[$type] ?? throw new MalformedMessage(
            'unexpected message type ' . json_encode(
                substr($type, 0, 40),
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
            ),
        );

        return $class::fromPayload($decoded);
    }
}
