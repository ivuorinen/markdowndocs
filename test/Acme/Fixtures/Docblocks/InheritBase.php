<?php

declare(strict_types=1);

namespace Acme\Fixtures\Docblocks;

abstract class InheritBase
{
    /**
     * Documented on the parent.
     *
     * @param  int $n a number
     * @return string
     */
    abstract public function work(int $n): string;
}
