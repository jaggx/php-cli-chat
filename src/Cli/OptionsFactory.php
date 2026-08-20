<?php

namespace PhpCliChat\Cli;

use PhpCliChat\Client\ClientOptions;
use PhpCliChat\Server\ServerOptions;

class OptionsFactory
{
    private const string DEBUG = '--debug';
    private const string CLIENT_ENV_FILE = '.client.env';
    private const string SERVER_ENV_FILE = '.server.env';

    /**
     * @param array<int, string> $argv
     */
    public static function client(array $argv, ?string $envPath = null): ClientOptions
    {
        self::helpIfAsked(
            $argv,
            'client.php',
            'Connect a TUI client to a chat server.',
            self::usages(ClientOptions::HOST, ClientOptions::PORT),
        );

        $env = self::env($envPath, self::CLIENT_ENV_FILE);

        return new ClientOptions(
            self::option($argv, 'host', $env->get('HOST') ?? ClientOptions::HOST),
            self::option($argv, 'port', $env->get('PORT') ?? ClientOptions::PORT),
        );
    }

    /**
     * @param array<int, string> $argv
     */
    public static function server(array $argv, ?string $envPath = null): ServerOptions
    {
        self::helpIfAsked($argv, 'server.php', 'Serve terminal chat over TCP.', [
            ...self::usages(ServerOptions::HOST, ServerOptions::PORT),
            self::DEBUG => 'Print every line exchanged with a client',
        ]);

        $env = self::env($envPath, self::SERVER_ENV_FILE);

        return new ServerOptions(
            self::option($argv, 'host', $env->get('HOST') ?? ServerOptions::HOST),
            self::option($argv, 'port', $env->get('PORT') ?? ServerOptions::PORT),
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

    private static function env(?string $path, string $file): EnvFile
    {
        return EnvFile::read($path ?? \dirname(__DIR__, 2) . '/' . $file);
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
