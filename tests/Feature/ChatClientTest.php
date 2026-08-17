<?php

use Amp\Socket;
use PhpCliChat\Client\ChatClient;
use Tests\Support\FakeUi;
use Tests\Support\LineCollector;

use function Amp\async;
use function Amp\delay;

/**
 * Stands in for the server: hands back the fake ui and the accepted peer.
 *
 * @return array{FakeUi, Socket\Socket, Socket\ServerSocket}
 */
function connectFakeClient(): array
{
    $listener = Socket\listen('127.0.0.1:0');

    $ui = new FakeUi();
    $client = new ChatClient($ui);

    async(fn () => $client->connect((string) $listener->getAddress()))->ignore();

    $peer = $listener->accept();

    if (null === $peer) {
        throw new RuntimeException('The client never connected.');
    }

    return [$ui, $peer, $listener];
}

it('announces the connection', function () {
    [$ui, , $listener] = connectFakeClient();
    delay(0.05);

    expect($ui->appended[0])->toContain('Connected to 127.0.0.1:');

    $ui->stop();
    $listener->close();
});

it('appends messages coming from the server', function () {
    [$ui, $peer, $listener] = connectFakeClient();

    $peer->write("client 7: hi there\n");
    delay(0.05);

    expect($ui->appended)->toContain('client 7: hi there');

    $ui->stop();
    $listener->close();
});

it('sends a submitted message to the server', function () {
    [$ui, $peer, $listener] = connectFakeClient();
    $sent = new LineCollector($peer);
    delay(0.05);

    $ui->submit('hello world');
    delay(0.05);

    expect($sent->lines)->toBe(['hello world']);

    $ui->stop();
    $listener->close();
});

it('echoes a submitted message locally', function () {
    // The server never sends it back, so without this the user sees nothing.
    [$ui, , $listener] = connectFakeClient();
    delay(0.05);

    $ui->submit('hello world');

    expect($ui->appended)->toContain('me: hello world');

    $ui->stop();
    $listener->close();
});

it('stops the ui when the server hangs up', function () {
    [$ui, $peer, $listener] = connectFakeClient();
    delay(0.05);

    expect($ui->stopped)->toBeFalse();

    $peer->close();
    delay(0.05);

    expect($ui->stopped)->toBeTrue();
    expect($ui->appended)->toContain('*** connection closed by server');

    $listener->close();
});
