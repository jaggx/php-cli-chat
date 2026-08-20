<?php

use Amp\ByteStream\WritableBuffer;
use Amp\ByteStream\WritableStream;
use Amp\Socket;
use PhpCliChat\Protocol\Codec\Decoder;
use PhpCliChat\Protocol\Codec\Encoder;
use PhpCliChat\Protocol\Message;
use PhpCliChat\Server\ChatServer;
use PhpCliChat\Server\Hub;
use PhpCliChat\Server\ServerOptions;
use Revolt\EventLoop;
use Tests\Support\LineCollector;
use Tests\TestCase;

use function Amp\async;
use function Amp\delay;

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
 * Binds an OS-assigned port and accepts in the background.
 *
 * Whatever the server prints is buffered rather than printed, so a test run
 * stays readable; pass a buffer of your own to read it back.
 *
 * @return array{ChatServer, string}
 */
function startChatServer(
    Hub $hub = new Hub(),
    bool $debug = false,
    WritableStream $log = new WritableBuffer(),
): array {
    $server = new ChatServer();
    $server->setOptions(new ServerOptions('127.0.0.1', '0', $debug));
    $server->setHub($hub);
    $server->setLog($log);

    $address = (string) $server->listen();

    async(fn () => $server->serve())->ignore();

    return [$server, $address];
}

/**
 * The lines a server logged about its traffic, with the operational noise
 * around them ("Listening on ...", "client 0 connected ...") left out.
 *
 * @return list<string>
 */
function loggedTraffic(WritableBuffer $log): array
{
    $log->end();

    return array_values(array_filter(
        explode(PHP_EOL, $log->buffer()),
        static fn (string $line) => str_contains($line, ' -> '),
    ));
}

/**
 * Settles before returning: the server accepts on its own fiber, so every
 * caller needs the loop to run before it can write or assert.
 *
 * @return array{Socket\Socket, LineCollector}
 */
function connectClient(string $address): array
{
    $socket = Socket\connect($address);
    $collector = new LineCollector($socket);

    delay(0.05);

    return [$socket, $collector];
}

function envFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'php-cli-chat-env-');

    if (false === $path) {
        throw new RuntimeException('could not create a temporary settings file');
    }

    file_put_contents($path, $contents);
    register_shutdown_function(static fn () => @unlink($path));

    return $path;
}

/**
 * Newline included.
 */
function wireLine(Message $message): string
{
    return Encoder::encode($message) . "\n";
}

/**
 * Decoding rather than matching hand-written JSON keeps a key-order change
 * from failing a test. A malformed line throws and fails it.
 *
 * @param string[] $lines
 *
 * @return Message[]
 */
function decodeFromServer(array $lines): array
{
    $decoder = Decoder::toClient();

    return array_map(fn (string $line) => $decoder->decode($line), $lines);
}

/**
 * @param string[] $lines
 *
 * @return Message[]
 */
function decodeFromClient(array $lines): array
{
    $decoder = Decoder::toServer();

    return array_map(fn (string $line) => $decoder->decode($line), $lines);
}
