<?php

namespace Tests\Support;

use PhpCliChat\Protocol\WireLog;

class WireLogCollector implements WireLog
{
    /**
     * @var list<string>
     */
    public array $sentLines = [];

    /**
     * @var list<string>
     */
    public array $receivedLines = [];

    public function sent(string $line): void
    {
        $this->sentLines[] = $line;
    }

    public function received(string $line): void
    {
        $this->receivedLines[] = $line;
    }
}
