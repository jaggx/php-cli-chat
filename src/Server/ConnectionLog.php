<?php

namespace PhpCliChat\Server;

use Amp\ByteStream\WritableStream;
use PhpCliChat\Cli\Sanitizer;
use PhpCliChat\Protocol\WireLog;

readonly class ConnectionLog implements WireLog
{
    public function __construct(
        private WritableStream $out,
        private int            $id,
    ) {}

    public function sent(string $line): void
    {
        $this->out->write("server -> client $this->id $line" . PHP_EOL);
    }

    public function received(string $line): void
    {
        $this->out->write("client $this->id -> server " . Sanitizer::sanitize($line) . PHP_EOL);
    }
}
