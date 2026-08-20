<?php

use PhpCliChat\Cli\EnvFile;
use Symfony\Component\Dotenv\Exception\FormatException;

it('reads a setting per line', function () {
    $env = EnvFile::read(envFile("HOST=0.0.0.0\nPORT=9000\n"));

    expect([$env->get('HOST'), $env->get('PORT')])->toBe(['0.0.0.0', '9000']);
});

it('has nothing to say about a key that is not there', function () {
    expect(EnvFile::read(envFile("HOST=0.0.0.0\n"))->get('PORT'))->toBeNull();
});

it('reads a file that is not there as an empty one', function () {
    expect(EnvFile::read('/does/not/exist/.server.env')->get('HOST'))->toBeNull();
});

it('refuses a file it cannot parse, saying where', function (string $contents, string $reason) {
    expect(fn () => EnvFile::read(envFile($contents)))
        ->toThrow(FormatException::class, $reason);
})->with([
    'no equals sign' => ["HOST\n", 'Missing = in the environment variable declaration'],
    'a space before the =' => ["HOST = 0.0.0.0\n", 'Whitespace characters are not supported after the variable name'],
    'an unclosed quote' => ["HOST=\"0.0.0.0\n", 'Missing quote to end the value'],
]);

it('skips what carries no setting', function (string $line) {
    $env = EnvFile::read(envFile("$line\nPORT=9000\n"));

    expect($env->get('PORT'))->toBe('9000');
})->with([
    'a comment' => ['# HOST=0.0.0.0'],
    'a blank line' => [''],
    'spaces only' => ['   '],
]);

it('reads a quoted value without its quotes', function (string $written) {
    expect(EnvFile::read(envFile("HOST=$written\n"))->get('HOST'))->toBe('0.0.0.0');
})->with([
    'double' => ['"0.0.0.0"'],
    'single' => ["'0.0.0.0'"],
]);

it('keeps everything after the first =', function () {
    expect(EnvFile::read(envFile("HOST=a=b\n"))->get('HOST'))->toBe('a=b');
});

it('lets the last of a repeated key win', function () {
    expect(EnvFile::read(envFile("PORT=9000\nPORT=1337\n"))->get('PORT'))->toBe('1337');
});

it('reads a file written with CRLF line endings', function () {
    expect(EnvFile::read(envFile("HOST=0.0.0.0\r\nPORT=9000\r\n"))->get('PORT'))->toBe('9000');
});

it('reads the dotenv syntax a shell user expects', function (string $contents, string $expected) {
    expect(EnvFile::read(envFile($contents))->get('HOST'))->toBe($expected);
})->with([
    'an export prefix' => ["export HOST=0.0.0.0\n", '0.0.0.0'],
    'a trailing comment' => ["HOST=0.0.0.0 # the address\n", '0.0.0.0'],
    'a reference to an earlier setting' => ["SUFFIX=local\nHOST=chat.\${SUFFIX}\n", 'chat.local'],
]);
