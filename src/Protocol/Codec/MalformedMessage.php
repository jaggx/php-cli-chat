<?php

namespace PhpCliChat\Protocol\Codec;

/**
 * No throw reaches a connection fiber, so no connection closes.
 */
class MalformedMessage extends \RuntimeException {}
