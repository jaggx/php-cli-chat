<?php

use Amp\Socket;
use PhpCliChat\Protocol\Message\Broadcast;
use PhpCliChat\Protocol\Message\Chat;
use PhpCliChat\Protocol\MessageChannel;
use PhpCliChat\Protocol\Transport\LineStream;
use PhpCliChat\Protocol\Unreadable;
use Tests\Support\LineCollector;
use Tests\Support\MessageCollector;
use Tests\Support\WireLogCollector;

use function Amp\delay;

it('writes one JSON line per message', function () {
    [$local, $remote] = Socket\createSocketPair();
    $collected = new LineCollector($remote);

    MessageChannel::forClient(new LineStream($local))->send(new Chat('hello'));
    delay(0.05);

    expect($collected->lines)->toBe(['{"type":"chat","text":"hello"}']);
});

it('yields decoded messages', function () {
    [$local, $remote] = Socket\createSocketPair();
    $received = new MessageCollector(MessageChannel::forServer(new LineStream($local)));

    $remote->write('{"type":"chat","text":"hello"}' . "\n");
    delay(0.05);

    expect($received->messages)->toEqual([new Chat('hello')]);
});

it('decodes in its own direction', function () {
    [$local, $remote] = Socket\createSocketPair();
    $received = new MessageCollector(MessageChannel::forClient(new LineStream($local)));

    $remote->write('{"type":"chat","from":"alice","text":"hi"}' . "\n");
    delay(0.05);

    expect($received->messages)->toEqual([new Broadcast('alice', 'hi')]);
});

it('yields Unreadable for a line it cannot decode', function () {
    [$local, $remote] = Socket\createSocketPair();
    $received = new MessageCollector(MessageChannel::forServer(new LineStream($local)));

    $remote->write("garbage\n");
    delay(0.05);

    expect($received->messages)->toHaveCount(1);
    expect($received->messages[0])->toBeInstanceOf(Unreadable::class);
    expect($received->messages[0]->reason)->not->toBe('');
});

it('keeps reading after a line it cannot decode', function () {
    // The regression test for the whole error-handling design: a malformed
    // line must not end the iteration, so one bad peer cannot mute itself.
    [$local, $remote] = Socket\createSocketPair();
    $received = new MessageCollector(MessageChannel::forServer(new LineStream($local)));

    $remote->write("garbage\n");
    $remote->write('{"type":"chat","text":"still here"}' . "\n");
    delay(0.05);

    expect($received->messages)->toHaveCount(2);
    expect($received->messages[0])->toBeInstanceOf(Unreadable::class);
    expect($received->messages[1])->toEqual(new Chat('still here'));
});

it('closes its stream', function () {
    [$local, $remote] = Socket\createSocketPair();

    MessageChannel::forServer(new LineStream($local))->close();

    expect($local->isClosed())->toBeTrue();
    expect($remote->read())->toBeNull();
});

it('ignores a blank line rather than reporting it as malformed', function () {
    // A bare "\n" is a framing artifact, not a protocol error; the server
    // used to ignore it silently before this branch and must still do so.
    [$local, $remote] = Socket\createSocketPair();
    $received = new MessageCollector(MessageChannel::forServer(new LineStream($local)));

    $remote->write("\n");
    $remote->write('{"type":"chat","text":"hello"}' . "\n");
    delay(0.05);

    expect($received->messages)->toEqual([new Chat('hello')]);
});

it('ends its iteration when the peer hangs up', function () {
    [$local, $remote] = Socket\createSocketPair();
    $received = new MessageCollector(MessageChannel::forServer(new LineStream($local)));

    $remote->close();
    delay(0.05);

    expect($received->closed)->toBeTrue();
    expect($received->messages)->toBeEmpty();   // a clean hangup is not a malformed line
});

it('taps the line it sends', function () {
    [$local, $remote] = Socket\createSocketPair();
    $log = new WireLogCollector();

    MessageChannel::forClient(new LineStream($local), $log)->send(new Chat('hello'));
    delay(0.05);

    expect($log->sentLines)->toBe(['{"type":"chat","text":"hello"}']);
    expect($log->receivedLines)->toBeEmpty();
});

it('taps a received line as it arrived, decodable or not', function () {
    // The tap is what a debug mode prints, so it has to show the bytes rather
    // than a re-encoding of what the decoder made of them: a forged field or a
    // line no decoder can read is exactly what someone is looking for.
    [$local, $remote] = Socket\createSocketPair();
    $log = new WireLogCollector();
    new MessageCollector(MessageChannel::forServer(new LineStream($local), $log));

    $remote->write('{"type":"chat","from":9,"text":"hello"}' . "\n");
    $remote->write("garbage\n");
    delay(0.05);

    expect($log->receivedLines)->toBe([
        '{"type":"chat","from":9,"text":"hello"}',
        'garbage',
    ]);
    expect($log->sentLines)->toBeEmpty();
});

it('does not tap a blank line', function () {
    [$local, $remote] = Socket\createSocketPair();
    $log = new WireLogCollector();
    new MessageCollector(MessageChannel::forServer(new LineStream($local), $log));

    $remote->write("\n");
    delay(0.05);

    expect($log->receivedLines)->toBeEmpty();
});

it('reports the address of the peer', function () {
    $listener = Socket\listen('127.0.0.1:0');
    $channel = MessageChannel::forClient(LineStream::connect((string) $listener->getAddress()));

    expect((string) $channel->getRemoteAddress())->toBe((string) $listener->getAddress());

    $channel->close();
    $listener->close();
});
