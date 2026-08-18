<?php

namespace Tests\Support;

use Amp\Socket;

use function Amp\async;
use function Amp\ByteStream\splitLines;

class LineCollector
{
    /**
     * @var string[]
     */
    public array $lines = [];

    /**
     * True once the other end hung up. The collector owns the only read on the
     * socket, so a test cannot look for EOF itself without a PendingReadError.
     */
    public bool $closed = false;

    public function __construct(Socket\Socket $socket)
    {
        async(function () use ($socket) {
            foreach (splitLines($socket) as $line) {
                $this->lines[] = $line;
            }

            $this->closed = true;
        })->ignore();
    }
}
