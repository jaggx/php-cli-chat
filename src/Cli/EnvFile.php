<?php

namespace PhpCliChat\Cli;

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\FormatException;

readonly class EnvFile
{
    /**
     * @param array<array-key, mixed> $values
     */
    private function __construct(
        private array $values,
    ) {}

    /**
     * @throws FormatException
     */
    public static function read(string $path): self
    {
        if (!is_file($path) || false === $contents = @file_get_contents($path)) {
            return new self([]);
        }

        return new self(new Dotenv()->parse($contents, $path));
    }

    public function get(string $key): ?string
    {
        $value = $this->values[$key] ?? null;

        return \is_string($value) ? $value : null;
    }
}
