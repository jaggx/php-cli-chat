<?php

use PhpCliChat\Cli\Sanitizer;

it('makes untrusted text safe to render', function (string $input, string $expected) {
    expect(Sanitizer::sanitize($input))->toBe($expected);
})->with([
    // The introducer goes, the payload survives as visible text.
    'ANSI colour codes' => ["\x1b[31mRED\x1b[0m", '[31mRED[0m'],
    'OSC window title' => ["a\x1b]0;pwned\x07b", 'a]0;pwnedb'],
    'BEL' => ["bell\x07here", 'bellhere'],
    '8-bit CSI' => ["\xc2\x9b31m", '31m'],
    'DEL' => ["a\x7fb", 'ab'],

    // One log entry has to stay one line, or a peer could forge a notice.
    'embedded LF' => ["line1\n*** client 3 joined the chat", 'line1*** client 3 joined the chat'],
    'trailing CR' => ["text\r", 'text'],

    // Legitimate content is left alone.
    'tabs' => ["col1\tcol2", "col1\tcol2"],
    'emoji' => ["hi \xF0\x9F\x98\x80", "hi \xF0\x9F\x98\x80"],
    'accents' => ['perché', 'perché'],
    'plain text' => ['hello everyone', 'hello everyone'],
    'empty' => ['', ''],

    // The renderer measures widths per character, so broken UTF-8 goes.
    'invalid UTF-8' => ["bad\xff\xfeend", 'badend'],

    // Stripping is single-pass: removing the control byte between a stray 0xC2
    // and a following 0x80-0x9F byte must not splice them into a live C1
    // introducer. Discarding malformed UTF-8 first is what prevents it.
    'spliced 8-bit CSI' => ["\xc2\x01\x9b31m", '31m'],
    'spliced 8-bit OSC' => ["\xc2\x02\x9dpwned", 'pwned'],
    'spliced via DEL' => ["\xc2\x7f\x9b2J", '2J'],
    'spliced via ESC' => ["\xc2\x1b\x9bH", 'H'],
]);
