<?php

declare(strict_types=1);

namespace Acme\Fixtures\Natives;

/**
 * Takes a PHP class as a parameter but returns none, so only the parameter
 * branch can produce the php.net link for it.
 */
class TakesNativeParam
{
    public function accept(\ArrayObject $items): void
    {
        unset($items);
    }
}
