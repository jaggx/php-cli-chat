<?php

namespace PhpCliChat\Server;

use Amp\ByteStream\WritableStream;
use Amp\Socket;

class Hub
{
    /**
     * @var Connection[]
     */
    private array $connections = [];
    private int $nextID = 0;

    public function __construct(
        private readonly Roster $roster = new Roster(),
    ) {}

    public function accept(Socket\Socket $socket, ?WritableStream $debug = null): Connection
    {
        $id = $this->nextID;
        $this->nextID = $id + 1;

        return $this->connections[$id] = Connection::accept($socket, $id, $debug);
    }

    public function disconnect(int $id): void
    {
        $connection = $this->connections[$id] ?? null;

        unset($this->connections[$id]);
        $this->roster->release($id);

        $connection?->close();
    }

    /**
     * @return Connection[]
     */
    public function all(): array
    {
        return $this->connections;
    }

    /**
     * @throws NameRefused
     */
    public function claim(int $id, string $name): void
    {
        $this->roster->claim($id, $name);
    }

    public function label(int $id): string
    {
        return $this->roster->label($id);
    }
}
