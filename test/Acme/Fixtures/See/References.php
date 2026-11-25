<?php

declare(strict_types=1);

namespace Acme\Fixtures\See;

/**
 * Points readers elsewhere.
 *
 * @see https://example.com/guide The guide
 * @see https://example.com/plain
 * @see \Acme\Fixtures\See\References::act() for how it acts, which takes a
 *      second line to say.
 * @see
 */
class References
{
    /**
     * Acts on something.
     *
     * @see https://example.com/method Method notes
     * @see
     */
    public function act(): void
    {
    }
}
