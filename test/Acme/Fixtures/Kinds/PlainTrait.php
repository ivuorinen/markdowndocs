<?php

namespace Acme\Fixtures\Kinds;

/**
 * A trait must be discovered by a directory scan, not only when named directly.
 */
trait PlainTrait
{
    public function fromTrait(): string
    {
        return 'x';
    }
}
