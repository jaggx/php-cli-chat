<?php

use Amp\Socket;
use PhpCliChat\Server\ChatServer;
use PhpCliChat\Server\Hub;
use Revolt\EventLoop;
use Tests\Support\LineCollector;
use Tests\TestCase;

use function Amp\async;

pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Event loop isolation
|--------------------------------------------------------------------------
|
| Every test leaves fibers parked on reads and fire-and-forget writes behind.
| Left alone they resume inside the next test and write to sockets it never
| opened, so we cancel whatever is still registered. Swapping in a fresh
| driver would be tidier, but Revolt refuses once the loop has run, and
| amphp/phpunit-util v3 needs PHPUnit 9 while Pest 5 ships PHPUnit 13.
|
*/

pest()->afterEach(function () {
    foreach (EventLoop::getIdentifiers() as $callbackId) {
        EventLoop::cancel($callbackId);
    }
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Boots a server on an OS-assigned port and starts accepting in the
 * background. Returns the server and the address to connect to.
 *
 * @return array{ChatServer, string}
 */
function startChatServer(?Hub $hub = null): array
{
    $server = new ChatServer($hub ?? new Hub());
    $address = (string) $server->listen('127.0.0.1:0');

    async(fn () => $server->serve($address))->ignore();

    return [$server, $address];
}

/**
 * Connects a client and starts collecting whatever the server sends it.
 *
 * @return array{Socket\Socket, LineCollector}
 */
function connectClient(string $address): array
{
    $socket = Socket\connect($address);

    return [$socket, new LineCollector($socket)];
}
