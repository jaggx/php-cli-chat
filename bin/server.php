<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpCliChat\Cli\OptionsFactory;
use PhpCliChat\Server\ChatServer;

use function Amp\async;
use function Amp\ByteStream\getStdout;
use function Amp\trapSignal;

$server = new ChatServer();
$server->setOptions(OptionsFactory::server($argv ?? []));

async(function () use ($server) {
    trapSignal([SIGINT, SIGTERM], reference: false);

    getStdout()->write('Shutting down' . PHP_EOL);

    $server->stop();
})->ignore();

$server->serve();
