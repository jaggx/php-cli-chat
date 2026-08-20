<?php

namespace PhpCliChat\Server;

use Amp\ByteStream\WritableStream;
use Amp\Socket;
use PhpCliChat\Protocol\Message\Broadcast;
use PhpCliChat\Protocol\Message\Chat;
use PhpCliChat\Protocol\Message\Login;
use PhpCliChat\Protocol\Message\Notice;
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
            $connection = $this->hub->accept($client, $this->options->debug ? $this->log : null);

            $this->log->write("client $connection->id connected from {$connection->getRemoteAddress()}" . PHP_EOL);

            async(function () use ($connection) {
                try {
                    $this->handleConnection($connection);
                } catch (\Throwable $t) {
                    getStderr()->write("client $connection->id failed: {$t->getMessage()}" . PHP_EOL);
                } finally {
                    $this->hub->disconnect($connection->id);
                }
            });
        }
    }

    public function stop(): void
    {
        $this->server?->close();

        foreach ($this->hub->all() as $connection) {
            $this->hub->disconnect($connection->id);
        }
    }

    private function bind(): Socket\ServerSocket
    {
        return $this->server ??= Socket\listen("{$this->options->host}:{$this->options->port}");
    }

    private function handleConnection(Connection $connection): void
    {
        foreach ($connection->receive() as $message) {
            match (true) {
                $message instanceof Unreadable => $this->logUnreadable($connection, $message),
                $message instanceof Login => $this->login($connection, $message),
                $message instanceof Chat => $this->chat($connection, $message),
                default => null,
            };
        }
    }

    private function logUnreadable(Connection $connection, Unreadable $message): void
    {
        getStderr()->write("client $connection->id sent a malformed message: $message->reason" . PHP_EOL);
    }

    private function chat(Connection $connection, Chat $message): void
    {
        $text = trim($message->text);

        if ('' === $text) {
            return;
        }

        $this->broadcast($connection, new Broadcast($this->hub->label($connection->id), $text));
    }

    private function login(Connection $connection, Login $message): void
    {
        try {
            $this->hub->claim($connection->id, $message->name);
        } catch (NameRefused $e) {
            $connection->send(new Notice($e->getMessage()));

            return;
        }

        $connection->send(new Notice("you are now {$this->hub->label($connection->id)}"));
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
