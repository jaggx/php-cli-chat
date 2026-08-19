<?php

namespace PhpCliChat\Client;

class CommandList
{
    private const array DESCRIPTIONS = [
        Command::HELP => 'show this list',
        Command::QUIT => 'close the client, like Esc',
    ];

    /**
     * @return list<string>
     */
    public static function lines(): array
    {
        $lines = [];

        foreach (self::DESCRIPTIONS as $name => $description) {
            $lines[] = "/$name — $description";
        }

        return $lines;
    }
}
