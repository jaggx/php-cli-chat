<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpCliChat\Cli\OptionsFactory;
use PhpCliChat\Client\ChatClient;
use Symfony\Component\Dotenv\Exception\FormatException;

use function Amp\ByteStream\getStderr;

try {
    $options = OptionsFactory::client($argv ?? []);
} catch (FormatException $e) {
    getStderr()->write($e->getMessage() . PHP_EOL);

    exit(1);
}

$client = new ChatClient();
$client->setOptions($options);

$client->connect();
