<?php

declare(strict_types=1);

namespace Acme\Fixtures\Docblocks;

class InheritGrandchild extends InheritMiddle
{
    /**
     * {@inheritDoc}
     */
    public function work(int $n): string
    {
        return (string)$n;
    }
}
