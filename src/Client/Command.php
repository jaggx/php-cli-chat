<?php

namespace PhpCliChat\Client;

readonly class Command
{
    public const null CHAT = null;
    public const string HELP = 'help';
    public const string QUIT = 'quit';

    public function __construct(
        public string $name,
        public string $args,
    ) {}

    public static function parse(string $input): ?self
    {
        if (!str_starts_with($input, '/')) {
            return self::CHAT;
        }

        $parts = explode(' ', substr($input, 1), 2);

        return new self(strtolower($parts[0]), trim($parts[1] ?? ''));
    }
}
