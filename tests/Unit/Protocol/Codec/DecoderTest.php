<?php

use PhpCliChat\Protocol\Codec\Decoder;
use PhpCliChat\Protocol\Codec\Encoder;
use PhpCliChat\Protocol\Codec\MalformedMessage;
use PhpCliChat\Protocol\Message\Broadcast;
use PhpCliChat\Protocol\Message\Chat;
use PhpCliChat\Protocol\Message\Login;
use PhpCliChat\Protocol\Message\Logout;
use PhpCliChat\Protocol\Message\Notice;

it('round-trips a client chat message', function () {
    $line = Encoder::encode(new Chat('hi'));

    expect(Decoder::toServer()->decode($line))->toEqual(new Chat('hi'));
});

it('round-trips a server broadcast', function () {
    $line = Encoder::encode(new Broadcast('alice', 'hi'));

    expect(Decoder::toClient()->decode($line))->toEqual(new Broadcast('alice', 'hi'));
});

it('rejects a line that is not JSON', function () {
    expect(fn () => Decoder::toServer()->decode('not json'))
        ->toThrow(MalformedMessage::class);
});

it('rejects JSON that is not an object', function (string $line) {
    expect(fn () => Decoder::toServer()->decode($line))
        ->toThrow(MalformedMessage::class);
})->with(['"5"', '5', 'null', 'true', '[1,2]']);

it('rejects a missing or non-string type', function (string $line) {
    expect(fn () => Decoder::toServer()->decode($line))
        ->toThrow(MalformedMessage::class);
})->with(['{"text":"hi"}', '{"type":5,"text":"hi"}', '{"type":null,"text":"hi"}']);

it('rejects a type this direction does not accept', function () {
    // notice is the server's to send, never to receive.
    expect(fn () => Decoder::toServer()->decode('{"type":"notice","text":"hi"}'))
        ->toThrow(MalformedMessage::class);
});

it('rejects a wrong-typed field', function (string $line) {
    expect(fn () => Decoder::toServer()->decode($line))
        ->toThrow(MalformedMessage::class);
})->with(['{"type":"chat","text":5}', '{"type":"chat"}']);

it('rejects a broadcast with a non-string from', function () {
    // from carries a label now, not an id. A v0.2 server's integer breaks a
    // v0.3 client exactly as this does.
    expect(fn () => Decoder::toClient()->decode('{"type":"chat","from":7,"text":"hi"}'))
        ->toThrow(MalformedMessage::class);
});

it('ignores unknown keys', function () {
    // Forward compatibility: an older peer must survive a newer peer's fields.
    expect(Decoder::toServer()->decode('{"type":"chat","text":"hi","colour":"red"}'))
        ->toEqual(new Chat('hi'));
});

it('drops a client-supplied from', function () {
    // The wire cannot be trusted to say who sent something; the server stamps
    // the connection id it read the bytes on.
    expect(Decoder::toServer()->decode('{"type":"chat","from":9,"text":"x"}'))
        ->toEqual(new Chat('x'));
});

it('scopes decoding to whose decoder it is', function () {
    // Both directions use "chat" with different shapes. A client's decoder
    // must not turn another client's line into a broadcast just because the
    // type string matches.
    expect(fn () => Decoder::toClient()->decode('{"type":"chat","text":"hi"}'))
        ->toThrow(MalformedMessage::class);
});

it('never lets a client-supplied type inject control bytes into the reason', function () {
    // json_decode turns  into a real ESC byte. If that byte reached the
    // server operator's terminal via the exception message it could rewrite
    // the window title, change colours, or ring the bell.
    try {
        Decoder::toServer()->decode('{"type":"[31mred","text":"hi"}');
        expect(false)->toBeTrue('expected a MalformedMessage to be thrown');
    } catch (MalformedMessage $e) {
        expect($e->getMessage())->not->toContain("\x1b");
    }
});

it('truncates a very long client-supplied type in the reason', function () {
    $line = json_encode(['type' => str_repeat('a', 100_000), 'text' => 'hi'], JSON_THROW_ON_ERROR);

    try {
        Decoder::toServer()->decode($line);
        expect(false)->toBeTrue('expected a MalformedMessage to be thrown');
    } catch (MalformedMessage $e) {
        expect(strlen($e->getMessage()))->toBeLessThan(100);
    }
});

it('round-trips a client login', function () {
    $line = Encoder::encode(new Login('alice'));

    expect(Decoder::toServer()->decode($line))->toEqual(new Login('alice'));
});

it('rejects a login with a missing or non-string name', function (string $line) {
    // Only the field's type is a protocol matter. What makes a good name is
    // Server\Roster's business, and gets an answer rather than a dropped line.
    expect(fn () => Decoder::toServer()->decode($line))
        ->toThrow(MalformedMessage::class);
})->with(['{"type":"login","name":5}', '{"type":"login"}', '{"type":"login","name":null}']);

it('accepts a login whose name breaks the naming rules', function () {
    // Decoding is not validation: an invalid name has to reach the server so
    // the server can say why it is invalid.
    expect(Decoder::toServer()->decode('{"type":"login","name":"al-ice"}'))
        ->toEqual(new Login('al-ice'));
});

it('round-trips a server notice', function () {
    $line = Encoder::encode(new Notice('you are now alice'));

    expect(Decoder::toClient()->decode($line))->toEqual(new Notice('you are now alice'));
});

it('rejects a notice with a missing or non-string text', function (string $line) {
    expect(fn () => Decoder::toClient()->decode($line))
        ->toThrow(MalformedMessage::class);
})->with(['{"type":"notice"}', '{"type":"notice","text":5}']);

it('rejects a login arriving at a client', function () {
    // login is the other direction's type. Each registry holds only its own.
    expect(fn () => Decoder::toClient()->decode('{"type":"login","name":"alice"}'))
        ->toThrow(MalformedMessage::class);
});

it('round-trips a client logout', function () {
    $line = Encoder::encode(new Logout());

    expect(Decoder::toServer()->decode($line))->toEqual(new Logout());
});

it('ignores whatever a logout carries', function () {
    // /logout takes no argument, and the connection it arrived on is who it
    // is about, so there is no field a client could get wrong.
    expect(Decoder::toServer()->decode('{"type":"logout","name":"bob"}'))
        ->toEqual(new Logout());
});

it('rejects a logout arriving at a client', function () {
    expect(fn () => Decoder::toClient()->decode('{"type":"logout"}'))
        ->toThrow(MalformedMessage::class);
});
