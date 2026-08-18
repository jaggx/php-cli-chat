<?php

namespace PhpCliChat\Protocol\Codec;

use PhpCliChat\Protocol\Message;

class Encoder
{
    /**
     * @throws \JsonException
     */
    public static function encode(Message $message): string
    {
        return json_encode(
            ['type' => $message->type()] + $message->payload(),
            JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }
}
