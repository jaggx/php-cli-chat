<?php

namespace PhpCliChat\Protocol;

interface WireLog
{
    public function sent(string $line): void;

    public function received(string $line): void;
}
