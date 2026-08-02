<?php

declare(strict_types=1);

namespace Acme\Fixtures\Docblocks;

/**
 * Reach the maintainer.
 * maintainer@example.com is the address to use.
 * This trailing sentence must survive.
 */
class TagShapes
{
    /**
     * Counts things.
     *
     * @params string $wrong
     * @param  string $label What to count
     */
    public function mistypedTag(string $label): void
    {
    }
}
