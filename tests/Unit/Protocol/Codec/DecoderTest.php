<?php

use PhpCliChat\Protocol\Codec\Decoder;
use PhpCliChat\Protocol\Codec\Encoder;
use PhpCliChat\Protocol\Codec\MalformedMessage;
use PhpCliChat\Protocol\Message\Broadcast;
use PhpCliChat\Protocol\Message\Chat;

it('round-trips a client chat message', function () {
    $line = Encoder::encode(new Chat('hi'));

    expect(Decoder::forServer()->decode($line))->toEqual(new Chat('hi'));
});

it('round-trips a server broadcast', function () {
    $line = Encoder::encode(new Broadcast(7, 'hi'));

    expect(Decoder::forClient()->decode($line))->toEqual(new Broadcast(7, 'hi'));
});

it('rejects a line that is not JSON', function () {
    expect(fn () => Decoder::forServer()->decode('not json'))
        ->toThrow(MalformedMessage::class);
});

it('rejects JSON that is not an object', function (string $line) {
    expect(fn () => Decoder::forServer()->decode($line))
        ->toThrow(MalformedMessage::class);
})->with(['"5"', '5', 'null', 'true', '[1,2]']);

it('rejects a missing or non-string type', function (string $line) {
    expect(fn () => Decoder::forServer()->decode($line))
        ->toThrow(MalformedMessage::class);
})->with(['{"text":"hi"}', '{"type":5,"text":"hi"}', '{"type":null,"text":"hi"}']);

it('rejects a type this direction does not accept', function () {
    expect(fn () => Decoder::forServer()->decode('{"type":"notice","text":"hi"}'))
        ->toThrow(MalformedMessage::class);
});

it('rejects a wrong-typed field', function (string $line) {
    expect(fn () => Decoder::forServer()->decode($line))
        ->toThrow(MalformedMessage::class);
})->with(['{"type":"chat","text":5}', '{"type":"chat"}']);

it('rejects a broadcast with a non-integer from', function () {
    expect(fn () => Decoder::forClient()->decode('{"type":"chat","from":"7","text":"hi"}'))
        ->toThrow(MalformedMessage::class);
});

it('ignores unknown keys', function () {
    // Forward compatibility: an older peer must survive a newer peer's fields.
    expect(Decoder::forServer()->decode('{"type":"chat","text":"hi","colour":"red"}'))
        ->toEqual(new Chat('hi'));
});

it('drops a client-supplied from', function () {
    // The wire cannot be trusted to say who sent something; the server stamps
    // the connection id it read the bytes on.
    expect(Decoder::forServer()->decode('{"type":"chat","from":9,"text":"x"}'))
        ->toEqual(new Chat('x'));
});

it('scopes decoding to whose decoder it is', function () {
    // Both directions use "chat" with different shapes. A client's decoder
    // must not turn another client's line into a broadcast just because the
    // type string matches.
    expect(fn () => Decoder::forClient()->decode('{"type":"chat","text":"hi"}'))
        ->toThrow(MalformedMessage::class);
});

it('never lets a client-supplied type inject control bytes into the reason', function () {
    // json_decode turns  into a real ESC byte. If that byte reached the
    // server operator's terminal via the exception message it could rewrite
    // the window title, change colours, or ring the bell.
    try {
        Decoder::forServer()->decode('{"type":"[31mred","text":"hi"}');
        expect(false)->toBeTrue('expected a MalformedMessage to be thrown');
    } catch (MalformedMessage $e) {
        expect($e->getMessage())->not->toContain("\x1b");
    }
});

it('truncates a very long client-supplied type in the reason', function () {
    $line = json_encode(['type' => str_repeat('a', 100_000), 'text' => 'hi'], JSON_THROW_ON_ERROR);

    try {
        Decoder::forServer()->decode($line);
        expect(false)->toBeTrue('expected a MalformedMessage to be thrown');
    } catch (MalformedMessage $e) {
        expect(strlen($e->getMessage()))->toBeLessThan(100);
    }
});
