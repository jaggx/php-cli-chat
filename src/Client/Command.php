<?php

namespace PhpCliChat\Client;

readonly class Command
{
    public const null CHAT = null;
    public const string HELP = 'help';
    public const string LOGIN = 'login';
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

        // A whitespace run, not one space: /login   alice gives alice, while an
        // interior tab survives into the argument for the server to refuse.
        $parts = preg_split('/\s+/', substr($input, 1), 2) ?: [''];

        return new self(strtolower($parts[0]), trim($parts[1] ?? ''));
    }
}
