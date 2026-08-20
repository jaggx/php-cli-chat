<?php

namespace PhpCliChat\Server;

class Roster
{
    private const string ANONYMOUS = 'Anonymous';
    private const int MAX = 20;

    private const array RESERVED = ['anonymous', 'me'];

    /**
     * @var array<int, string> connection id → name, as it will display
     */
    private array $names = [];

    public function label(int $id): string
    {
        return $this->names[$id] ?? self::ANONYMOUS;
    }

    /**
     * @throws NameRefused
     */
    public function claim(int $id, string $name): void
    {
        if (isset($this->names[$id])) {
            throw new NameRefused("you are already logged in as {$this->names[$id]}");
        }

        $name = self::wellFormed($name);

        self::refuseIfReserved($name);

        if ($this->taken($name)) {
            throw new NameRefused("the name $name is taken");
        }

        $this->names[$id] = $name;
    }

    // The bool is /logout's answer; the disconnect path has no use for it.
    public function release(int $id): bool
    {
        $held = isset($this->names[$id]);

        unset($this->names[$id]);

        return $held;
    }

    private function taken(string $name): bool
    {
        return array_any($this->names, fn($existing) => strtolower($existing) === strtolower($name));
    }

    /**
     * @throws NameRefused
     */
    private static function wellFormed(string $name): string
    {
        $name = preg_replace('/ +/', ' ', trim($name)) ?? '';

        // ctype_alnum is false for an empty string and ASCII-only in the C
        // locale, so this one call also refuses '', '   ' and 'José'. That
        // makes strlen an exact character count.
        if (\strlen($name) > self::MAX || !ctype_alnum(str_replace(' ', '', $name))) {
            throw new NameRefused('a name must be 1 to 20 letters, digits or spaces');
        }

        return $name;
    }

    /**
     * @throws NameRefused
     */
    private static function refuseIfReserved(string $name): void
    {
        if (in_array(strtolower($name), self::RESERVED, true)) {
            throw new NameRefused("$name is not a name you can take");
        }
    }
}
