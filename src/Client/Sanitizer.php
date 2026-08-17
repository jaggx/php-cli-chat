<?php

namespace PhpCliChat\Client;

class Sanitizer
{
    public static function sanitize(string $line): string
    {
        if ('' !== $line && false === preg_match('//u', $line)) {
            $line = (string)@iconv('UTF-8', 'UTF-8//IGNORE', $line);
        }

        return preg_replace("/[\x00-\x08\x0a-\x1f\x7f]|\xc2[\x80-\x9f]/", '', $line) ?? '';
    }
}
