<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpCliChat\Cli\AddressOptions;
use PhpCliChat\Server\ChatServer;

use function Amp\async;
use function Amp\ByteStream\getStdout;
use function Amp\trapSignal;

$address = AddressOptions::fromArgv(
    $argv ?? [],
    basename(__FILE__),
    'Serve terminal chat over TCP.',
);

$server = new ChatServer();

async(function () use ($server) {
    trapSignal([SIGINT, SIGTERM], reference: false);

    getStdout()->write('Shutting down' . PHP_EOL);

    $server->stop();
})->ignore();

$server->serve($address);
