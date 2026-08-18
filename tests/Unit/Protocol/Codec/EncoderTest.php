<?php

use PhpCliChat\Protocol\Codec\Decoder;
use PhpCliChat\Protocol\Codec\Encoder;
use PhpCliChat\Protocol\Message\Broadcast;
use PhpCliChat\Protocol\Message\Chat;

it('encodes what a client sends', function () {
    expect(Encoder::encode(new Chat('hello everyone')))
        ->toBe('{"type":"chat","text":"hello everyone"}');
});

it('encodes what a server sends', function () {
    expect(Encoder::encode(new Broadcast(0, 'hello everyone')))
        ->toBe('{"type":"chat","from":0,"text":"hello everyone"}');
});

it('puts the type first, whatever the payload holds', function () {
    // The type has to survive a payload that also carries a "type" key, since
    // decoding reads it before anything else.
    expect(Encoder::encode(new Broadcast(7, 'hi')))
        ->toStartWith('{"type":"chat"');
});

it('keeps a newline in the text from breaking the framing', function () {
    // Serialization and framing stay independent: json_encode escapes the
    // newline, so LineStream still sees exactly one line.
    $line = Encoder::encode(new Chat("two\nlines"));

    expect($line)->not->toContain("\n");
    expect(Decoder::forServer()->decode($line))->toEqual(new Chat("two\nlines"));
});

it('substitutes invalid UTF-8 rather than throwing', function () {
    $line = Encoder::encode(new Chat("caf\xE9"));

    expect(json_validate($line))->toBeTrue();
    expect(Decoder::forServer()->decode($line))->toEqual(new Chat("caf\u{FFFD}"));
});
