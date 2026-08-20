<?php

use PhpCliChat\Protocol\Codec\Decoder;
use PhpCliChat\Protocol\Codec\Encoder;
use PhpCliChat\Protocol\Message\Broadcast;
use PhpCliChat\Protocol\Message\Chat;
use PhpCliChat\Protocol\Message\Login;
use PhpCliChat\Protocol\Message\Notice;

it('encodes what a client sends', function () {
    expect(Encoder::encode(new Chat('hello everyone')))
        ->toBe('{"type":"chat","text":"hello everyone"}');
});

it('encodes what a server sends', function () {
    expect(Encoder::encode(new Broadcast('alice', 'hello everyone')))
        ->toBe('{"type":"chat","from":"alice","text":"hello everyone"}');
});

it('puts the type first, whatever the payload holds', function () {
    // The type has to survive a payload that also carries a "type" key, since
    // decoding reads it before anything else.
    expect(Encoder::encode(new Broadcast('alice', 'hi')))
        ->toStartWith('{"type":"chat"');
});

it('keeps a newline in the text from breaking the framing', function () {
    // Serialization and framing stay independent: json_encode escapes the
    // newline, so LineStream still sees exactly one line.
    $line = Encoder::encode(new Chat("two\nlines"));

    expect($line)->not->toContain("\n");
    expect(Decoder::toServer()->decode($line))->toEqual(new Chat("two\nlines"));
});

it('substitutes invalid UTF-8 rather than throwing', function () {
    $line = Encoder::encode(new Chat("caf\xE9"));

    expect(json_validate($line))->toBeTrue();
    expect(Decoder::toServer()->decode($line))->toEqual(new Chat("caf\u{FFFD}"));
});

it('encodes a login', function () {
    expect(Encoder::encode(new Login('alice')))
        ->toBe('{"type":"login","name":"alice"}');
});

it('encodes a notice', function () {
    expect(Encoder::encode(new Notice('you are now alice')))
        ->toBe('{"type":"notice","text":"you are now alice"}');
});
