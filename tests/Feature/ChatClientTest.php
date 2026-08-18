<?php

use Amp\Socket;
use PhpCliChat\Client\ChatClient;
use PhpCliChat\Client\ClientOptions;
use PhpCliChat\Protocol\Message\Broadcast;
use PhpCliChat\Protocol\Message\Chat;
use Tests\Support\FakeUi;
use Tests\Support\LineCollector;

use function Amp\async;
use function Amp\delay;

/**
 * @return array{FakeUi, Socket\Socket, Socket\ServerSocket}
 */
function connectFakeClient(): array
{
    $listener = Socket\listen('127.0.0.1:0');

    $address = $listener->getAddress();

    $ui = new FakeUi();
    $client = new ChatClient();
    $client->setOptions(new ClientOptions($address->getAddress(), (string) $address->getPort()));
    $client->setUi($ui);

    async(fn () => $client->connect())->ignore();

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
    // Rendering stays a client concern: the sender arrives as data and the
    // client is what turns it into a line of text.
    [$ui, $peer, $listener] = connectFakeClient();

    $peer->write(wireLine(new Broadcast(7, 'hi there')));
    delay(0.05);

    expect($ui->appended)->toContain('client 7: hi there');

    $ui->stop();
    $listener->close();
});

it('ignores a line it cannot decode', function () {
    // Unreadable falls through the instanceof and is dropped: a version
    // mismatch degrades into a quiet session, not a crash.
    [$ui, $peer, $listener] = connectFakeClient();
    delay(0.05);

    $before = count($ui->appended);

    $peer->write("garbage\n");
    delay(0.05);

    expect($ui->appended)->toHaveCount($before);
    expect($ui->stopped)->toBeFalse();

    $ui->stop();
    $listener->close();
});

it('sends a submitted message to the server', function () {
    [$ui, $peer, $listener] = connectFakeClient();
    $sent = new LineCollector($peer);
    delay(0.05);

    $ui->submit('hello world');
    delay(0.05);

    expect(decodeFromClient($sent->lines))->toEqual([new Chat('hello world')]);

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
