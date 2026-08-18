<?php

namespace PhpCliChat\Server;

use Amp\Socket;

class Hub
{
    /**
     * @var Connection[]
     */
    private array $connections = [];
    private int $nextID = 0;

    public function add(Socket\Socket $socket): Connection
    {
        $id = $this->nextID;
        $this->nextID = $id + 1;

        return $this->connections[$id] = Connection::accept($socket, $id);
    }

    public function remove(int $id): void
    {
        unset($this->connections[$id]);
    }

    /**
     * @return Connection[]
     */
    public function all(): array
    {
        return $this->connections;
    }
}
