<?php

use PhpCliChat\Cli\OptionsFactory;
use PhpCliChat\Client\ClientOptions;
use PhpCliChat\Server\ServerOptions;
use Symfony\Component\Dotenv\Exception\FormatException;

const NO_ENV_FILE = '/does/not/exist/.server.env';

/**
 * The two halves of the address, as the factory read them.
 *
 * @param array<int, string> $options
 *
 * @return array{string, string}
 */
function hostAndPort(array $options, string $envPath = NO_ENV_FILE): array
{
    $parsed = OptionsFactory::server(['bin/server.php', ...$options], $envPath);

    return [$parsed->host, $parsed->port];
}

it('reads host and port as the two separate options they are', function (array $options, array $expected) {
    expect(hostAndPort($options))->toBe($expected);
})->with([
    'no options' => [[], ['127.0.0.1', '1337']],
    'host only' => [['--host=0.0.0.0'], ['0.0.0.0', '1337']],
    'port only' => [['--port=9000'], ['127.0.0.1', '9000']],
    'both' => [['--host=0.0.0.0', '--port=9000'], ['0.0.0.0', '9000']],
    'order is irrelevant' => [['--port=9000', '--host=0.0.0.0'], ['0.0.0.0', '9000']],
    'first one wins' => [['--host=a', '--host=b'], ['a', '1337']],

    // Bracketing an IPv6 literal is the caller's job. Whatever they pass goes
    // through untouched, and amphp is what rejects it.
    'bracketed IPv6' => [['--host=[::1]'], ['[::1]', '1337']],
    'hostname' => [['--host=my-laptop.local'], ['my-laptop.local', '1337']],
    'nonsense amphp will reject' => [['--port=not-a-port'], ['127.0.0.1', 'not-a-port']],
]);

it('shrugs at anything it does not recognise', function (array $options, array $expected) {
    expect(hostAndPort($options))->toBe($expected);
})->with([
    'unknown option' => [['--verbose'], ['127.0.0.1', '1337']],
    'bare argument' => [['9000'], ['127.0.0.1', '1337']],
    'flag without a value' => [['--port'], ['127.0.0.1', '1337']],
    'empty value' => [['--host='], ['', '1337']],
]);

it('reads the same two options for a client', function () {
    $options = OptionsFactory::client(['bin/client.php', '--host=my-laptop', '--port=9000'], NO_ENV_FILE);

    expect([$options->host, $options->port])->toBe(['my-laptop', '9000']);
});

it('turns debug on only when the server is asked for it', function () {
    expect(OptionsFactory::server(['bin/server.php'], NO_ENV_FILE)->debug)->toBeFalse();
    expect(OptionsFactory::server(['bin/server.php', '--debug'], NO_ENV_FILE)->debug)->toBeTrue();
    expect(hostAndPort(['--debug', '--port=9000']))->toBe(['127.0.0.1', '9000']);
});

it('renders a usage line and an aligned list from the options it is given', function () {
    $usage = OptionsFactory::usage('server.php', 'Serve.', [
        '--host=HOST' => 'Address to use [default: 127.0.0.1]',
        '--debug' => 'Print every line',
    ]);

    expect($usage)->toBe(<<<USAGE
        Serve.

        usage: server.php [--host=HOST] [--debug]

          --host=HOST  Address to use [default: 127.0.0.1]
          --debug      Print every line

        USAGE);
});

it('falls back to the defaults each options object declares', function () {
    // A command line that says nothing has to produce what the object itself
    // would: the factory reads the defaults off the class rather than keeping
    // a second copy, so --help and the fallback cannot drift apart.
    expect(OptionsFactory::client(['bin/client.php'], NO_ENV_FILE))->toEqual(new ClientOptions());
    expect(OptionsFactory::server(['bin/server.php'], NO_ENV_FILE))->toEqual(new ServerOptions());
});

it('reads a setting the command line is silent about from the env file', function () {
    $env = envFile("HOST=0.0.0.0\nPORT=9000\n");

    expect(hostAndPort([], $env))->toBe(['0.0.0.0', '9000']);
});

it('lets a command-line option beat the env file', function () {
    $env = envFile("HOST=0.0.0.0\nPORT=9000\n");

    expect(hostAndPort(['--port=1234'], $env))->toBe(['0.0.0.0', '1234']);
});

it('falls back to the class default for a key the env file omits', function () {
    expect(hostAndPort([], envFile("PORT=9000\n")))->toBe(['127.0.0.1', '9000']);
});

it('reads a client setting the command line is silent about from the env file', function () {
    $options = OptionsFactory::client(['bin/client.php'], envFile("HOST=my-laptop\nPORT=9000\n"));

    expect([$options->host, $options->port])->toBe(['my-laptop', '9000']);
});

it('lets a command-line option beat the client env file', function () {
    $options = OptionsFactory::client(['bin/client.php', '--host=other'], envFile("HOST=my-laptop\nPORT=9000\n"));

    expect([$options->host, $options->port])->toBe(['other', '9000']);
});

it('falls back to the class default for a key the client env file omits', function () {
    $options = OptionsFactory::client(['bin/client.php'], envFile("PORT=9000\n"));

    expect([$options->host, $options->port])->toBe(['127.0.0.1', '9000']);
});

it('refuses a client env file it cannot parse', function () {
    expect(fn () => OptionsFactory::client(['bin/client.php'], envFile("HOST\n")))
        ->toThrow(FormatException::class);
});
