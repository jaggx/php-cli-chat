<?php

use Amp\Socket;
use PhpCliChat\Server\Hub;

function fakeSocket(): Socket\Socket
{
    [$socket] = Socket\createSocketPair();

    return $socket;
}

it('assigns incrementing ids', function () {
    $hub = new Hub();

    expect($hub->add(fakeSocket())->id)->toBe(0);
    expect($hub->add(fakeSocket())->id)->toBe(1);
});

it('stores the connection under the id it returned', function () {
    $hub = new Hub();

    $connection = $hub->add(fakeSocket());

    expect($hub->all())->toBe([$connection->id => $connection]);
});

it('forgets a removed connection', function () {
    $hub = new Hub();

    $hub->remove($hub->add(fakeSocket())->id);

    expect($hub->all())->toBeEmpty();
});

it('does not reuse the id of a removed connection', function () {
    $hub = new Hub();

    $hub->remove($hub->add(fakeSocket())->id);

    expect($hub->add(fakeSocket())->id)->toBe(1);
});

it('shrugs off removing an id it never had', function () {
    $hub = new Hub();

    $hub->remove(99);

    expect($hub->all())->toBeEmpty();
});
