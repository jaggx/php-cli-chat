<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpCliChat\Cli\AddressOptions;
use PhpCliChat\Client\ChatClient;

$address = AddressOptions::fromArgv(
    $argv ?? [],
    basename(__FILE__),
    'Connect a TUI client to a chat server.',
);

new ChatClient()->connect($address);
