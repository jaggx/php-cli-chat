<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpCliChat\Cli\OptionsFactory;
use PhpCliChat\Server\ChatServer;
use Symfony\Component\Dotenv\Exception\FormatException;

use function Amp\async;
use function Amp\ByteStream\getStderr;
use function Amp\ByteStream\getStdout;
use function Amp\trapSignal;

try {
    $options = OptionsFactory::server($argv ?? []);
} catch (FormatException $e) {
    getStderr()->write($e->getMessage() . PHP_EOL);

    exit(1);
}

$server = new ChatServer();
$server->setOptions($options);

async(function () use ($server) {
    trapSignal([SIGINT, SIGTERM], reference: false);

    getStdout()->write('Shutting down' . PHP_EOL);

    $server->stop();
})->ignore();

$server->serve();
