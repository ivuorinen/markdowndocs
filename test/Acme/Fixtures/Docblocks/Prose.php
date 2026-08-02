<?php

declare(strict_types=1);

namespace Acme\Fixtures\Docblocks;

/**
 * Area is width * height in pixels.
 */
class Prose
{
    /**
     * Multiplies 2 * 3 to get six.
     *
     * {@link https://example.com Example}
     *
     * @param string $name The name of the thing, which needs
     *                     quite a long explanation to describe.
     * @param int    $n    A short one.
     */
    public function wrapped(string $name, int $n): void
    {
    }

    /**
     * Returns a thing.
     *
     * @return string the rendered name
     */
    public function returnsWithDescription()
    {
    }

    /**
     * @deprecated
     */
    public function deprecatedWithoutMessage(): void
    {
    }
}
