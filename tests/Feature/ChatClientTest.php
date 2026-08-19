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

it('quits on /quit without telling the server', function () {
    // Nothing goes on the wire: closing the socket is what the server reads,
    // and LineStream::send does not await its write, so a message sent just
    // before close() could be lost anyway.
    [$ui, $peer, $listener] = connectFakeClient();
    $sent = new LineCollector($peer);
    delay(0.05);

    $ui->submit('/quit');
    delay(0.05);

    expect($ui->stopped)->toBeTrue();
    expect($sent->lines)->toBeEmpty();
    expect($ui->appended)->not->toContain('me: /quit');
    expect($sent->closed)->toBeTrue();

    $listener->close();
});

it('lists the commands on /help', function () {
    [$ui, $peer, $listener] = connectFakeClient();
    $sent = new LineCollector($peer);
    delay(0.05);

    $ui->submit('/help');
    delay(0.05);

    expect($ui->appended)->toContain('*** /help — show this list');
    expect($ui->appended)->toContain('*** /quit — close the client, like Esc');
    expect($ui->stopped)->toBeFalse();
    expect($sent->lines)->toBeEmpty();

    $ui->stop();
    $listener->close();
});

it('reports an unknown command locally', function () {
    // A typo stays in the typist's own log rather than going out to the room.
    [$ui, $peer, $listener] = connectFakeClient();
    $sent = new LineCollector($peer);
    delay(0.05);

    $ui->submit('/qut');
    delay(0.05);

    expect($ui->appended)->toContain('*** unknown command: /qut');
    expect($ui->stopped)->toBeFalse();
    expect($sent->lines)->toBeEmpty();

    $ui->stop();
    $listener->close();
});

it('truncates a long command name in the notice', function () {
    // Same 40-character cap Codec\Decoder puts on an unexpected type.
    [$ui, , $listener] = connectFakeClient();
    delay(0.05);

    $ui->submit('/' . str_repeat('x', 60));

    expect($ui->appended)->toContain('*** unknown command: /' . str_repeat('x', 40));

    $ui->stop();
    $listener->close();
});

it('truncates a long multibyte command name in the notice', function () {
    // A byte-wise cut can slice a multibyte character in half, leaving an
    // incomplete UTF-8 sequence that Sanitizer::sanitize() cannot repair and
    // Ui::append() then renders as an empty line.
    [$ui, , $listener] = connectFakeClient();
    delay(0.05);

    $ui->submit('/' . str_repeat('あ', 50));

    expect($ui->appended)->toContain('*** unknown command: /' . str_repeat('あ', 40));

    $ui->stop();
    $listener->close();
});

it('does not drop a command name that is the falsy string "0"', function () {
    // '0' is falsy in PHP, so a truthiness check would drop it from the notice.
    [$ui, , $listener] = connectFakeClient();
    delay(0.05);

    $ui->submit('/0');

    expect($ui->appended)->toContain('*** unknown command: /0');

    $ui->stop();
    $listener->close();
});

it('sends a message whose slash is not leading as chat', function () {
    [$ui, $peer, $listener] = connectFakeClient();
    $sent = new LineCollector($peer);
    delay(0.05);

    $ui->submit('why did you do /that?');
    delay(0.05);

    expect(decodeFromClient($sent->lines))->toEqual([new Chat('why did you do /that?')]);

    $ui->stop();
    $listener->close();
});
