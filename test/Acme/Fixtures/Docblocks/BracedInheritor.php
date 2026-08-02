<?php

declare(strict_types=1);

namespace Acme\Fixtures\Docblocks;

class BracedInheritor extends InheritBase
{
    /**
     * {@inheritDoc}
     */
    public function work(int $n): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function nothingToInheritFrom(): string
    {
        return '';
    }
}
