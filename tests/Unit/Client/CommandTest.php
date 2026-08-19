<?php

use PhpCliChat\Client\Command;

it('leaves ordinary chat alone', function (string $input) {
    expect(Command::parse($input))->toBe(Command::CHAT);
})->with([
    'plain text' => ['hello everyone'],
    // Recognition is positional: only a leading slash means a command.
    'a slash mid-sentence' => ['why did you do /that?'],
    'a slash inside a word' => ['me/you'],
]);

it('parses a leading slash into a name and args', function (string $input, string $name, string $args) {
    $command = Command::parse($input);

    expect($command)->toBeInstanceOf(Command::class);
    expect($command->name)->toBe($name);
    expect($command->args)->toBe($args);
})->with([
    'no args' => ['/quit', 'quit', ''],
    'case folded' => ['/QUIT', 'quit', ''],
    'args parsed even where the command ignores them' => ['/quit now please', 'quit', 'now please'],

    // Parsing is not gated on a known name, so a command this client does not
    // handle yet still arrives as a Command and is reported rather than sent.
    'an unhandled name still parses' => ['/nick alice', 'nick', 'alice'],

    // A bare slash has an empty name, which no match arm claims.
    'a bare slash' => ['/', '', ''],

    // A single space is the only separator while no command takes arguments.
    'a tab is not a separator' => ["/quit\tnow", "quit\tnow", ''],
]);
