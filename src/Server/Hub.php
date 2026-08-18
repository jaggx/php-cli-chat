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

    /**
     * @param ?WritableStream $debug passed on to the connection this builds,
     *                               which is what owns the id it logs under
     */
    public function add(Socket\Socket $socket, ?WritableStream $debug = null): Connection
    {
        $id = $this->nextID;
        $this->nextID = $id + 1;

        return $this->connections[$id] = Connection::accept($socket, $id, $debug);
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
