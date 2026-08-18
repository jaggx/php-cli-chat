<?php

use PhpCliChat\Cli\OptionsFactory;
use PhpCliChat\Client\ClientOptions;
use PhpCliChat\Server\ServerOptions;

/**
 * The two halves of the address, as the factory read them.
 *
 * @param array<int, string> $options
 *
 * @return array{string, string}
 */
function hostAndPort(array $options): array
{
    $parsed = OptionsFactory::server(['bin/server.php', ...$options]);

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
    $options = OptionsFactory::client(['bin/client.php', '--host=my-laptop', '--port=9000']);

    expect([$options->host, $options->port])->toBe(['my-laptop', '9000']);
});

it('turns debug on only when the server is asked for it', function () {
    expect(OptionsFactory::server(['bin/server.php'])->debug)->toBeFalse();
    expect(OptionsFactory::server(['bin/server.php', '--debug'])->debug)->toBeTrue();
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
    expect(OptionsFactory::client(['bin/client.php']))->toEqual(new ClientOptions());
    expect(OptionsFactory::server(['bin/server.php']))->toEqual(new ServerOptions());
});
