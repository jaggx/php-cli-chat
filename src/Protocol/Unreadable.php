<?php

namespace PhpCliChat\Protocol;

readonly class Unreadable
{
    public function __construct(
        public string $reason,
    ) {}
}
