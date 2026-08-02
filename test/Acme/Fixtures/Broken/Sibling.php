<?php

declare(strict_types=1);

namespace Acme\Fixtures\Broken;

/**
 * Loads cleanly. Must still be documented when its neighbour cannot be loaded.
 */
class Sibling
{
    public function works(): bool
    {
        return true;
    }
}
