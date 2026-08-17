<?php

namespace PhpCliChat\Cli;

class AddressOptions
{
    /**
     * @param array<int, string> $argv
     */
    public static function fromArgv(array $argv, string $script, string $description): string
    {
        if (in_array('--help', $argv, true)) {
            echo <<<HELP
                $description

                usage: $script [--host=HOST] [--port=PORT]

                  --host=HOST  Address to use [default: 127.0.0.1]
                  --port=PORT  TCP port [default: 1337]

                HELP;

            exit(0);
        }

        return self::option($argv, 'host', '127.0.0.1')
            . ':' . self::option($argv, 'port', '1337');
    }

    /**
     * @param array<int, string> $argv
     */
    private static function option(array $argv, string $name, string $default): string
    {
        foreach ($argv as $argument) {
            if (str_starts_with($argument, "--$name=")) {
                return substr($argument, strlen("--$name="));
            }
        }

        return $default;
    }
}
