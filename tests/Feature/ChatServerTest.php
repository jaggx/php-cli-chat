<?php

use Amp\Socket;
use PhpCliChat\Protocol\Message\Broadcast;
use PhpCliChat\Protocol\Message\Chat;
use PhpCliChat\Server\Hub;

use function Amp\delay;

it('does not send a message back to its sender', function () {
    [$server, $address] = startChatServer();

    [$alice, $aliceLines] = connectClient($address);
    delay(0.05);
    connectClient($address);
    delay(0.05);

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
    delay(0.05);
    [, $bobLines] = connectClient($address);
    delay(0.05);

    $alice->write(wireLine(new Chat('   ')));
    $alice->write(wireLine(new Chat('')));
    delay(0.05);

    expect($bobLines->lines)->toBeEmpty();

    $server->stop();
});

it('relays in both directions', function () {
    [$server, $address] = startChatServer();

    [$alice, $aliceLines] = connectClient($address);
    delay(0.05);
    [$bob, $bobLines] = connectClient($address);
    delay(0.05);

    $alice->write(wireLine(new Chat('from alice')));
    delay(0.05);
    $bob->write(wireLine(new Chat('from bob')));
    delay(0.05);

    expect(decodeFromServer($bobLines->lines))->toEqual([new Broadcast(0, 'from alice')]);
    expect(decodeFromServer($aliceLines->lines))->toEqual([new Broadcast(1, 'from bob')]);

    $server->stop();
});

it('stamps the sender itself rather than trusting the wire', function () {
    [$server, $address] = startChatServer();

    [, $aliceLines] = connectClient($address);
    delay(0.05);
    [$bob] = connectClient($address);
    delay(0.05);
    [, $carolLines] = connectClient($address);
    delay(0.05);

    // Bob claims to be Alice. The server knows which socket the bytes arrived
    // on and ignores the claim.
    $bob->write('{"type":"chat","from":0,"text":"not from alice"}' . "\n");
    delay(0.05);

    expect(decodeFromServer($carolLines->lines))->toEqual([new Broadcast(1, 'not from alice')]);
    // The forged from:0 must not make the server skip alice (id 0) as a
    // recipient by mistaking her for the sender.
    expect(decodeFromServer($aliceLines->lines))->toEqual([new Broadcast(1, 'not from alice')]);

    $server->stop();
});

it('survives a client that sends garbage', function () {
    // Malformed input is dropped and logged, never fatal: one bad client must
    // not disconnect itself or disturb its peers. Expect one "malformed
    // message" line on stderr while this test runs.
    [$server, $address] = startChatServer();

    [$alice, $aliceLines] = connectClient($address);
    delay(0.05);
    [, $bobLines] = connectClient($address);
    delay(0.05);

    $alice->write("garbage\n");
    delay(0.05);

    $alice->write(wireLine(new Chat('still here')));
    delay(0.05);

    expect($aliceLines->closed)->toBeFalse();
    expect(decodeFromServer($bobLines->lines))->toEqual([new Broadcast(0, 'still here')]);

    $server->stop();
});

it('forgets a client that disconnects', function () {
    $hub = new Hub();
    [$server, $address] = startChatServer($hub);

    [$alice] = connectClient($address);
    delay(0.05);

    expect($hub->all())->toHaveCount(1);

    $alice->close();
    delay(0.05);

    expect($hub->all())->toBeEmpty();

    $server->stop();
});

it('keeps serving the survivors after one client leaves', function () {
    [$server, $address] = startChatServer();

    [$alice] = connectClient($address);
    delay(0.05);
    [$bob, $bobLines] = connectClient($address);
    delay(0.05);
    [, $carolLines] = connectClient($address);
    delay(0.05);

    $bob->close();
    delay(0.05);

    $alice->write(wireLine(new Chat('still here')));
    delay(0.05);

    expect(decodeFromServer($carolLines->lines))->toEqual([new Broadcast(0, 'still here')]);
    expect($bobLines->lines)->toBeEmpty();

    $server->stop();
});

it('closes every connection when it stops', function () {
    [$server, $address] = startChatServer();

    [, $aliceLines] = connectClient($address);
    delay(0.05);
    [, $bobLines] = connectClient($address);
    delay(0.05);

    $server->stop();
    delay(0.05);

    expect($aliceLines->closed)->toBeTrue();
    expect($bobLines->closed)->toBeTrue();
});

it('empties the hub when it stops', function () {
    $hub = new Hub();
    [$server, $address] = startChatServer($hub);

    connectClient($address);
    delay(0.05);
    connectClient($address);
    delay(0.05);

    expect($hub->all())->toHaveCount(2);

    # No delay: stopping drains the register itself rather than waiting for each
    # connection fiber to unwind through its finally block.
    $server->stop();

    expect($hub->all())->toBeEmpty();
});

it('stops accepting new connections when it stops', function () {
    [$server, $address] = startChatServer();

    connectClient($address);
    delay(0.05);

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
    delay(0.05);

    # The second call finds a closed listener and an empty register. It has to
    # be a no-op rather than a throw: SIGINT then SIGTERM is an ordinary way to
    # quit an unresponsive server.
    $server->stop();
    $server->stop();
    delay(0.05);

    expect($aliceLines->closed)->toBeTrue();
});
