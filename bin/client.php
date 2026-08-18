<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpCliChat\Cli\OptionsFactory;
use PhpCliChat\Client\ChatClient;

$client = new ChatClient();
$client->setOptions(OptionsFactory::client($argv ?? []));

$client->connect();
