<?php

namespace PhpCliChat\Server;

use Amp\ByteStream\WritableStream;
use Amp\Socket;
use PhpCliChat\Protocol\Message\Broadcast;
use PhpCliChat\Protocol\Message\Chat;
use PhpCliChat\Protocol\Unreadable;

use function Amp\async;
use function Amp\ByteStream\getStderr;
use function Amp\ByteStream\getStdout;

class ChatServer
{
    private ?Socket\ServerSocket $server = null;

    private ServerOptions $options;

    private Hub $hub;

    private WritableStream $log;

    public function __construct()
    {
        $this->options = new ServerOptions();
        $this->hub = new Hub();
        $this->log = getStdout();
    }

    public function setOptions(ServerOptions $options): void
    {
        $this->options = $options;
    }

    public function setHub(Hub $hub): void
    {
        $this->hub = $hub;
    }

    public function setLog(WritableStream $log): void
    {
        $this->log = $log;
    }

    public function listen(): Socket\SocketAddress
    {
        return $this->bind()->getAddress();
    }

    public function serve(): void
    {
        $server = $this->bind();

        $this->log->write("Listening on {$server->getAddress()}" . PHP_EOL);

        while ($client = $server->accept()) {
            $connection = $this->hub->add($client, $this->options->debug ? $this->log : null);

            $this->log->write("client $connection->id connected from {$connection->getRemoteAddress()}" . PHP_EOL);

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

    private function bind(): Socket\ServerSocket
    {
        return $this->server ??= Socket\listen("{$this->options->host}:{$this->options->port}");
    }

    private function handleConnection(Connection $connection): void
    {
        foreach ($connection->receive() as $message) {
            if ($message instanceof Unreadable) {
                getStderr()->write("client $connection->id sent a malformed message: $message->reason" . PHP_EOL);
                continue;
            }

            // The server codec only ever produces Message\Chat today, so this cannot fire
            // yet; it is what PHPStan needs to narrow, and the landing spot for the next
            // client->server type.
            if (!$message instanceof Chat) {
                continue;
            }

            $text = trim($message->text);

            if ('' === $text) {
                continue;
            }

            $this->broadcast($connection, new Broadcast($connection->id, $text));
        }
    }

    private function broadcast(Connection $sender, Broadcast $message): void
    {
        foreach ($this->hub->all() as $peer) {
            if ($peer->id === $sender->id) {
                continue;
            }

            $peer->send($message);
        }
    }
}
