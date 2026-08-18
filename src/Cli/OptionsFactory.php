<?php

namespace PhpCliChat\Cli;

use PhpCliChat\Client\ClientOptions;
use PhpCliChat\Server\ServerOptions;

class OptionsFactory
{
    private const string DEBUG = '--debug';

    /**
     * @param array<int, string> $argv
     */
    public static function client(array $argv): ClientOptions
    {
        self::helpIfAsked(
            $argv,
            'client.php',
            'Connect a TUI client to a chat server.',
            self::usages(ClientOptions::HOST, ClientOptions::PORT),
        );

        return new ClientOptions(
            self::option($argv, 'host', ClientOptions::HOST),
            self::option($argv, 'port', ClientOptions::PORT),
        );
    }

    /**
     * @param array<int, string> $argv
     */
    public static function server(array $argv): ServerOptions
    {
        self::helpIfAsked($argv, 'server.php', 'Serve terminal chat over TCP.', [
            ...self::usages(ServerOptions::HOST, ServerOptions::PORT),
            self::DEBUG => 'Print every line exchanged with a client',
        ]);

        return new ServerOptions(
            self::option($argv, 'host', ServerOptions::HOST),
            self::option($argv, 'port', ServerOptions::PORT),
            debug: in_array(self::DEBUG, $argv, true),
        );
    }

    /**
     * @param array<string, string> $options
     */
    public static function usage(string $script, string $description, array $options): string
    {
        $usage = implode(' ', array_map(
            static fn (string $option) => "[$option]",
            array_keys($options),
        ));

        $list = implode(PHP_EOL, array_map(
            static fn (string $option, string $help) => sprintf('  %-11s  %s', $option, $help),
            array_keys($options),
            $options,
        ));

        return <<<USAGE
            $description

            usage: $script $usage

            $list

            USAGE;
    }

    /**
     * @return array<string, string>
     */
    private static function usages(string $host, string $port): array
    {
        return [
            '--host=HOST' => "Address to use [default: $host]",
            '--port=PORT' => "TCP port [default: $port]",
        ];
    }

    /**
     * @param array<int, string>    $argv
     * @param array<string, string> $options
     */
    private static function helpIfAsked(array $argv, string $script, string $description, array $options): void
    {
        if (!in_array('--help', $argv, true)) {
            return;
        }

        echo self::usage($script, $description, $options);

        exit(0);
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
