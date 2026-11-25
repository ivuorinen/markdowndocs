<?php

declare(strict_types=1);

namespace Acme\Fixtures\Types;

class SelfTyped
{
    public function merge(self $other): self
    {
        return $other;
    }
}
