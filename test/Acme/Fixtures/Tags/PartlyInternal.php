<?php

declare(strict_types=1);

namespace Acme\Fixtures\Tags;

class PartlyInternal
{
    public function published(): void
    {
    }

    /**
     * Documented unless --no-internal is given.
     *
     * @internal
     */
    public function internalHelper(): void
    {
    }
}
