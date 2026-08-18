<?php

namespace PhpCliChat\Server;

readonly class ServerOptions
{
    public const string HOST = '127.0.0.1';
    public const string PORT = '1337';

    public function __construct(
        public string $host = self::HOST,
        public string $port = self::PORT,
        public bool   $debug = false,
    ) {}
}
