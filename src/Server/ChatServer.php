<?php

namespace PhpCliChat\Server;

use Amp\Socket;

use function Amp\async;
use function Amp\ByteStream\getStderr;
use function Amp\ByteStream\getStdout;

class ChatServer
{
    private ?Socket\ServerSocket $server = null;

    public function __construct(
        private readonly Hub $hub = new Hub(),
    ) {}

    public function listen(string $address): Socket\SocketAddress
    {
        return $this->bind($address)->getAddress();
    }

    public function serve(string $address): void
    {
        $server = $this->bind($address);

        echo "Listening on " . $server->getAddress() . PHP_EOL;

        while ($client = $server->accept()) {
            $connection = $this->hub->add($client);

            getStdout()->write("client $connection->id connected from {$connection->getRemoteAddress()}" . PHP_EOL);

            async(function () use ($connection) {
                try {
                    $this->handleConnection($connection);
                } catch (\Throwable $t) {
                    getStderr()->write("client $connection->id failed: {$t->getMessage()}" . PHP_EOL);
                } finally {
                    $this->hub->remove($connection->id);
                    $connection->close();
                }
            });
        }
    }

    public function stop(): void
    {
        $this->server?->close();

        foreach ($this->hub->all() as $connection) {
            $this->hub->remove($connection->id);
            $connection->close();
        }
    }

    private function bind(string $address): Socket\ServerSocket
    {
        return $this->server ??= Socket\listen($address);
    }

    private function handleConnection(Connection $connection): void
    {
        foreach ($connection->receive() as $line) {
            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            $this->broadcast($connection, "client $connection->id: $line");
        }
    }

    private function broadcast(Connection $sender, string $message): void
    {
        foreach ($this->hub->all() as $peer) {
            if ($peer->id === $sender->id) {
                continue;
            }

            $peer->send($message);
        }
    }
}
