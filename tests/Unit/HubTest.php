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

    expect($hub->accept(fakeSocket())->id)->toBe(0);
    expect($hub->accept(fakeSocket())->id)->toBe(1);
});

it('stores the connection under the id it returned', function () {
    $hub = new Hub();

    $connection = $hub->accept(fakeSocket());

    expect($hub->all())->toBe([$connection->id => $connection]);
});

it('forgets a disconnected connection', function () {
    $hub = new Hub();

    $hub->disconnect($hub->accept(fakeSocket())->id);

    expect($hub->all())->toBeEmpty();
});

it('does not reuse the id of a disconnected connection', function () {
    $hub = new Hub();

    $hub->disconnect($hub->accept(fakeSocket())->id);

    expect($hub->accept(fakeSocket())->id)->toBe(1);
});

it('shrugs off disconnecting an id it never had', function () {
    $hub = new Hub();

    $hub->disconnect(99);

    expect($hub->all())->toBeEmpty();
});

it('frees a claimed name when its connection is disconnected', function () {
    // Releasing inside remove() is what stops a name outliving its socket.
    $hub = new Hub();
    $alice = $hub->accept(fakeSocket());
    $hub->claim($alice->id, 'alice');

    $hub->disconnect($alice->id);

    expect($hub->label($alice->id))->toBe('Anonymous');

    $next = $hub->accept(fakeSocket());
    $hub->claim($next->id, 'alice');

    expect($hub->label($next->id))->toBe('alice');
});

it('closes the socket of the connection it disconnects', function () {
    // disconnect() is the inverse of accept(), so closing is its job rather
    // than the caller's.
    [$socket] = Socket\createSocketPair();
    $hub = new Hub();

    $hub->disconnect($hub->accept($socket)->id);

    expect($socket->isClosed())->toBeTrue();
});
