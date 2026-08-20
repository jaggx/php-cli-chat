<?php

namespace PhpCliChat\Client;

class CommandList
{
    private const array DESCRIPTIONS = [
        Command::HELP => 'show this list',
        Command::LOGIN => 'set the name peers see',
        Command::QUIT => 'close the client, like Esc',
    ];

    // Keyed by the same constants as the descriptions, so a renamed command
    // cannot leave a stale usage behind.
    private const array ARGUMENTS = [Command::LOGIN => '<username>'];

    /**
     * @return list<string>
     */
    public static function lines(): array
    {
        $lines = [];

        foreach (self::DESCRIPTIONS as $name => $description) {
            $arguments = self::ARGUMENTS[$name] ?? '';

            $lines[] = '' === $arguments
                ? "/$name — $description"
                : "/$name $arguments — $description";
        }

        return $lines;
    }
}
