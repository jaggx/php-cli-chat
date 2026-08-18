<?php

namespace PhpCliChat\Client;

readonly class ClientOptions
{
    public const string HOST = '127.0.0.1';
    public const string PORT = '1337';

    public function __construct(
        public string $host = self::HOST,
        public string $port = self::PORT,
    ) {}
}
