<?php

use Amp\Socket;
use PhpCliChat\Protocol\MessageStream;
use Tests\Support\LineCollector;

use function Amp\async;
use function Amp\delay;

it('sends a message to the peer', function () {
    [$local, $remote] = Socket\createSocketPair();
    $collected = new LineCollector($remote);

    (new MessageStream($local))->send('hello');
    delay(0.05);

    expect($collected->lines)->toBe(['hello']);
});

it('terminates each message with exactly one newline', function () {
    [$local, $remote] = Socket\createSocketPair();
    $stream = new MessageStream($local);

    $stream->send('one');
    $stream->send('two');
    delay(0.05);

    expect((string) $remote->read())->toBe("one\ntwo\n");
});

it('reassembles a message split across chunks', function () {
    [$local, $remote] = Socket\createSocketPair();

    $received = [];
    async(function () use ($local, &$received) {
        foreach ((new MessageStream($local))->receive() as $message) {
            $received[] = $message;
        }
    })->ignore();

    // TCP guarantees order, never message boundaries: the third message
    // arrives in two pieces and still has to come out whole.
    $remote->write("one\ntwo\nthr");
    delay(0.05);
    $remote->write("ee\n");
    delay(0.05);

    expect($received)->toBe(['one', 'two', 'three']);
});

it('tolerates a peer that terminates with CRLF', function () {
    [$local, $remote] = Socket\createSocketPair();

    $received = [];
    async(function () use ($local, &$received) {
        foreach ((new MessageStream($local))->receive() as $message) {
            $received[] = $message;
        }
    })->ignore();

    $remote->write("windows\r\n");
    delay(0.05);

    expect($received)->toBe(['windows']);
});

it('stops receiving once the peer hangs up', function () {
    [$local, $remote] = Socket\createSocketPair();

    $ended = false;
    async(function () use ($local, &$ended) {
        foreach ((new MessageStream($local))->receive() as $ignored) {
            // drain
        }

        $ended = true;
    })->ignore();

    delay(0.05);
    expect($ended)->toBeFalse();

    $remote->close();
    delay(0.05);

    expect($ended)->toBeTrue();
});

it('closes its socket', function () {
    [$local, $remote] = Socket\createSocketPair();

    (new MessageStream($local))->close();

    expect($local->isClosed())->toBeTrue();
    expect($remote->read())->toBeNull();
});

it('reports the address of the peer', function () {
    $listener = Socket\listen('127.0.0.1:0');
    $stream = MessageStream::connect((string) $listener->getAddress());

    expect((string) $stream->getRemoteAddress())->toBe((string) $listener->getAddress());

    $stream->close();
    $listener->close();
});
