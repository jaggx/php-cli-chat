<?php

use Amp\ByteStream\WritableBuffer;
use Amp\Socket;
use PhpCliChat\Protocol\Message\Broadcast;
use PhpCliChat\Protocol\Message\Chat;
use PhpCliChat\Protocol\Message\Login;
use PhpCliChat\Protocol\Message\Logout;
use PhpCliChat\Protocol\Message\Notice;
use PhpCliChat\Server\Hub;

use function Amp\delay;

it('does not send a message back to its sender', function () {
    [$server, $address] = startChatServer();

    [$alice, $aliceLines] = connectClient($address);
    connectClient($address);

    $alice->write(wireLine(new Chat('hello everyone')));
    delay(0.05);

    expect($aliceLines->lines)->toBeEmpty();

    $server->stop();
});

it('ignores whitespace-only text', function () {
    // The UI guards against blank input too, but the server cannot trust a
    // client to have one.
    [$server, $address] = startChatServer();

    [$alice] = connectClient($address);
    [, $bobLines] = connectClient($address);

    $alice->write(wireLine(new Chat('   ')));
    $alice->write(wireLine(new Chat('')));
    delay(0.05);

    expect($bobLines->lines)->toBeEmpty();

    $server->stop();
});

it('relays in both directions', function () {
    [$server, $address] = startChatServer();

    [$alice, $aliceLines] = connectClient($address);
    [$bob, $bobLines] = connectClient($address);

    $alice->write(wireLine(new Chat('from alice')));
    delay(0.05);
    $bob->write(wireLine(new Chat('from bob')));
    delay(0.05);

    expect(decodeFromServer($bobLines->lines))->toEqual([new Broadcast('Anonymous', 'from alice')]);
    expect(decodeFromServer($aliceLines->lines))->toEqual([new Broadcast('Anonymous', 'from bob')]);

    $server->stop();
});

it('stamps the sender itself rather than trusting the wire', function () {
    [$server, $address] = startChatServer();

    [, $aliceLines] = connectClient($address);
    [$bob] = connectClient($address);
    [, $carolLines] = connectClient($address);

    $bob->write(wireLine(new Login('bob')));
    delay(0.05);

    // Bob claims the label alice. The server knows which socket the bytes
    // arrived on and stamps the name that socket holds.
    $bob->write('{"type":"chat","from":"alice","text":"not from alice"}' . "\n");
    delay(0.05);

    expect(decodeFromServer($carolLines->lines))->toEqual([new Broadcast('bob', 'not from alice')]);
    expect(decodeFromServer($aliceLines->lines))->toEqual([new Broadcast('bob', 'not from alice')]);

    $server->stop();
});

it('survives a client that sends garbage', function () {
    // Malformed input is dropped and logged, never fatal: one bad client must
    // not disconnect itself or disturb its peers. Expect one "malformed
    // message" line on stderr while this test runs.
    [$server, $address] = startChatServer();

    [$alice, $aliceLines] = connectClient($address);
    [, $bobLines] = connectClient($address);

    $alice->write("garbage\n");
    delay(0.05);

    $alice->write(wireLine(new Chat('still here')));
    delay(0.05);

    expect($aliceLines->closed)->toBeFalse();
    expect(decodeFromServer($bobLines->lines))->toEqual([new Broadcast('Anonymous', 'still here')]);

    $server->stop();
});

it('forgets a client that disconnects', function () {
    $hub = new Hub();
    [$server, $address] = startChatServer($hub);

    [$alice] = connectClient($address);

    expect($hub->all())->toHaveCount(1);

    $alice->close();
    delay(0.05);

    expect($hub->all())->toBeEmpty();

    $server->stop();
});

it('keeps serving the survivors after one client leaves', function () {
    [$server, $address] = startChatServer();

    [$alice] = connectClient($address);
    [$bob, $bobLines] = connectClient($address);
    [, $carolLines] = connectClient($address);

    $bob->close();
    delay(0.05);

    $alice->write(wireLine(new Chat('still here')));
    delay(0.05);

    expect(decodeFromServer($carolLines->lines))->toEqual([new Broadcast('Anonymous', 'still here')]);
    expect($bobLines->lines)->toBeEmpty();

    $server->stop();
});

it('logs both directions of the traffic in debug mode', function () {
    $log = new WritableBuffer();
    [$server, $address] = startChatServer(debug: true, log: $log);

    [$alice] = connectClient($address);
    connectClient($address);

    $alice->write(wireLine(new Chat('hello everyone')));
    delay(0.05);

    $server->stop();
    delay(0.05);

    expect(loggedTraffic($log))->toBe([
        'client 0 -> server {"type":"chat","text":"hello everyone"}',
        'server -> client 1 {"type":"chat","from":"Anonymous","text":"hello everyone"}',
    ]);
});

it('logs no traffic unless it is in debug mode', function () {
    $log = new WritableBuffer();
    [$server, $address] = startChatServer(log: $log);

    [$alice] = connectClient($address);
    connectClient($address);

    $alice->write(wireLine(new Chat('hello everyone')));
    delay(0.05);

    $server->stop();
    delay(0.05);

    expect(loggedTraffic($log))->toBeEmpty();
});

it('still relays what it logs', function () {
    // The tap watches the wire, it does not stand in it: turning debug on must
    // not consume, delay past its peers, or rewrite a single message.
    [$server, $address] = startChatServer(debug: true);

    [, $aliceLines] = connectClient($address);
    [$bob] = connectClient($address);

    $bob->write(wireLine(new Chat('hello')));
    delay(0.05);

    expect(decodeFromServer($aliceLines->lines))->toEqual([new Broadcast('Anonymous', 'hello')]);

    $server->stop();
});

it('closes every connection when it stops', function () {
    [$server, $address] = startChatServer();

    [, $aliceLines] = connectClient($address);
    [, $bobLines] = connectClient($address);

    $server->stop();
    delay(0.05);

    expect($aliceLines->closed)->toBeTrue();
    expect($bobLines->closed)->toBeTrue();
});

it('empties the hub when it stops', function () {
    $hub = new Hub();
    [$server, $address] = startChatServer($hub);

    connectClient($address);
    connectClient($address);

    expect($hub->all())->toHaveCount(2);

    # No delay: stopping drains the register itself rather than waiting for each
    # connection fiber to unwind through its finally block.
    $server->stop();

    expect($hub->all())->toBeEmpty();
});

it('stops accepting new connections when it stops', function () {
    [$server, $address] = startChatServer();

    connectClient($address);

    $server->stop();
    delay(0.05);

    # Connecting through the shared connector would retry three times with
    # exponential backoff and take six seconds to report the refusal. One
    # attempt is enough: a live listener accepts on the first try.
    $connector = new Socket\DnsSocketConnector();

    expect(fn () => $connector->connect($address))->toThrow(Socket\ConnectException::class);
});

it('can be stopped twice', function () {
    [$server, $address] = startChatServer();

    [, $aliceLines] = connectClient($address);

    # The second call finds a closed listener and an empty register. It has to
    # be a no-op rather than a throw: SIGINT then SIGTERM is an ordinary way to
    # quit an unresponsive server.
    $server->stop();
    $server->stop();
    delay(0.05);

    expect($aliceLines->closed)->toBeTrue();
});

it('answers a login with a notice, and tells no peer', function () {
    // Peers see the new label on the next thing she says. Announcing a join
    // would read as an oversight while leaving is still silent.
    [$server, $address] = startChatServer();

    [$alice, $aliceLines] = connectClient($address);
    [, $bobLines] = connectClient($address);

    $alice->write(wireLine(new Login('alice')));
    delay(0.05);

    expect(decodeFromServer($aliceLines->lines))->toEqual([new Notice('you are now alice')]);
    expect($bobLines->lines)->toBeEmpty();

    $server->stop();
});

it('stamps a logged-in name on what she says next', function () {
    [$server, $address] = startChatServer();

    [$alice] = connectClient($address);
    [, $bobLines] = connectClient($address);

    $alice->write(wireLine(new Login('  John  Doe  ')));
    delay(0.05);
    $alice->write(wireLine(new Chat('hello')));
    delay(0.05);

    // The notice confirms the normalised name, which is also what displays.
    expect(decodeFromServer($bobLines->lines))->toEqual([new Broadcast('John Doe', 'hello')]);

    $server->stop();
});

it('refuses a name another connection holds', function () {
    [$server, $address] = startChatServer();

    [$alice, $aliceLines] = connectClient($address);
    [$bob, $bobLines] = connectClient($address);

    $alice->write(wireLine(new Login('alice')));
    delay(0.05);
    $bob->write(wireLine(new Login('ALICE')));
    delay(0.05);
    $bob->write(wireLine(new Chat('still anonymous')));
    delay(0.05);

    expect(decodeFromServer($bobLines->lines))->toEqual([new Notice('the name ALICE is taken')]);

    // claim() throws before it writes, so a refused login leaves bob exactly
    // as anonymous as he was. Alice's own notice is on her socket too.
    expect(decodeFromServer($aliceLines->lines))->toEqual([
        new Notice('you are now alice'),
        new Broadcast('Anonymous', 'still anonymous'),
    ]);

    $server->stop();
});

it('frees a name when its connection closes', function () {
    // release() runs in the same finally that removes the connection from the
    // hub, so a name never outlives its socket.
    [$server, $address] = startChatServer();

    [$alice] = connectClient($address);
    [$bob, $bobLines] = connectClient($address);

    $alice->write(wireLine(new Login('alice')));
    delay(0.05);

    $alice->close();
    delay(0.05);

    $bob->write(wireLine(new Login('alice')));
    delay(0.05);

    expect(decodeFromServer($bobLines->lines))->toEqual([new Notice('you are now alice')]);

    $server->stop();
});

it('answers a logout with a notice, and tells no peer', function () {
    [$server, $address] = startChatServer();

    [$alice, $aliceLines] = connectClient($address);
    [, $bobLines] = connectClient($address);

    $alice->write(wireLine(new Login('alice')));
    delay(0.05);
    $alice->write(wireLine(new Logout()));
    delay(0.05);
    $alice->write(wireLine(new Chat('who am i')));
    delay(0.05);

    expect(decodeFromServer($aliceLines->lines))->toEqual([
        new Notice('you are now alice'),
        new Notice('you are now Anonymous'),
    ]);

    // Peers hear the label change on the next thing she says, as they did on
    // the way in, and nothing else.
    expect(decodeFromServer($bobLines->lines))->toEqual([new Broadcast('Anonymous', 'who am i')]);

    $server->stop();
});

it('refuses a logout from a connection that never logged in', function () {
    [$server, $address] = startChatServer();

    [$alice, $aliceLines] = connectClient($address);

    $alice->write(wireLine(new Logout()));
    delay(0.05);

    expect(decodeFromServer($aliceLines->lines))->toEqual([new Notice('you are not logged in')]);

    $server->stop();
});

it('frees a name for another connection on logout', function () {
    // The same release the disconnect path runs, so a name given up is free
    // immediately rather than at the end of the session.
    [$server, $address] = startChatServer();

    [$alice] = connectClient($address);
    [$bob, $bobLines] = connectClient($address);

    $alice->write(wireLine(new Login('alice')));
    delay(0.05);
    $bob->write(wireLine(new Login('alice')));
    delay(0.05);
    $alice->write(wireLine(new Logout()));
    delay(0.05);
    $bob->write(wireLine(new Login('alice')));
    delay(0.05);

    expect(decodeFromServer($bobLines->lines))->toEqual([
        new Notice('the name alice is taken'),
        new Notice('you are now alice'),
    ]);

    $server->stop();
});

it('lets a connection take a new name after logging out', function () {
    // /logout then /login is what a rename costs, and the scrollback ambiguity
    // it brings back is the reason claim() still refuses a rename outright.
    [$server, $address] = startChatServer();

    [$alice] = connectClient($address);
    [, $bobLines] = connectClient($address);

    $alice->write(wireLine(new Login('alice')));
    delay(0.05);
    $alice->write(wireLine(new Logout()));
    delay(0.05);
    $alice->write(wireLine(new Login('carol')));
    delay(0.05);
    $alice->write(wireLine(new Chat('still me')));
    delay(0.05);

    expect(decodeFromServer($bobLines->lines))->toEqual([new Broadcast('carol', 'still me')]);

    $server->stop();
});
