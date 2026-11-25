<?php

declare(strict_types=1);

namespace Acme\Fixtures\Natives;

/**
 * Returns PHP classes, so only the return-type branch can produce the php.net
 * links for them.
 */
class ReturnsNative
{
    public function iterator(): \ArrayIterator
    {
        return new \ArrayIterator([]);
    }

    /**
     * @return \SplStack[]
     */
    public function stacks(): array
    {
        return [];
    }
}
