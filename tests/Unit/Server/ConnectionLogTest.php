<?php

use Amp\ByteStream\WritableBuffer;
use PhpCliChat\Server\ConnectionLog;

/**
 * @param callable(ConnectionLog): void $write
 */
function logged(callable $write): string
{
    $out = new WritableBuffer();

    $write(new ConnectionLog($out, 3));

    $out->end();

    return $out->buffer();
}

it('names the direction each line travelled', function () {
    $output = logged(function (ConnectionLog $log) {
        $log->received('{"type":"chat","text":"hello"}');
        $log->sent('{"type":"chat","from":3,"text":"hi"}');
    });

    expect($output)->toBe(
        'client 3 -> server {"type":"chat","text":"hello"}' . PHP_EOL
        . 'server -> client 3 {"type":"chat","from":3,"text":"hi"}' . PHP_EOL
    );
});

it('makes a received line safe to print', function () {
    // A debug log puts a client's own bytes on the operator's terminal, so it
    // owes them the same scrubbing the client's UI gives what it renders.
    $output = logged(fn (ConnectionLog $log) => $log->received("\x1b[2Jgarbage"));

    expect($output)->toBe('client 3 -> server [2Jgarbage' . PHP_EOL);
});
