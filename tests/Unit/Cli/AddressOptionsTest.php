<?php

use PhpCliChat\Cli\AddressOptions;

/**
 * @param array<int, string> $options
 */
function address(array $options): string
{
    return AddressOptions::fromArgv(['bin/server.php', ...$options], 'server.php', 'Serve.');
}

it('joins host and port without inspecting either', function (array $options, string $expected) {
    expect(address($options))->toBe($expected);
})->with([
    'no options' => [[], '127.0.0.1:1337'],
    'host only' => [['--host=0.0.0.0'], '0.0.0.0:1337'],
    'port only' => [['--port=9000'], '127.0.0.1:9000'],
    'both' => [['--host=0.0.0.0', '--port=9000'], '0.0.0.0:9000'],
    'order is irrelevant' => [['--port=9000', '--host=0.0.0.0'], '0.0.0.0:9000'],
    'first one wins' => [['--host=a', '--host=b'], 'a:1337'],

    // Bracketing an IPv6 literal is the caller's job. Whatever they pass goes
    // through untouched, and amphp is what rejects it.
    'bracketed IPv6' => [['--host=[::1]'], '[::1]:1337'],
    'hostname' => [['--host=my-laptop.local'], 'my-laptop.local:1337'],
    'nonsense amphp will reject' => [['--port=not-a-port'], '127.0.0.1:not-a-port'],
]);

it('shrugs at anything it does not recognise', function (array $options, string $expected) {
    expect(address($options))->toBe($expected);
})->with([
    'unknown option' => [['--verbose'], '127.0.0.1:1337'],
    'bare argument' => [['9000'], '127.0.0.1:1337'],
    'flag without a value' => [['--port'], '127.0.0.1:1337'],
    'empty value' => [['--host='], ':1337'],
]);
