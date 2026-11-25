<?php

declare(strict_types=1);

namespace Acme\Fixtures\Types;

class SelfTyped
{
    public function merge(self $other): self
    {
        return $other;
    }

    public function fluent(): static
    {
        return $this;
    }

    /**
     * @return static
     */
    public function documentedFluent(): static
    {
        return $this;
    }
}
