<?php

declare(strict_types=1);

namespace Acme\Fixtures\Docblocks;

/**
 * Carries a class-level example.
 *
 * @example
 *   <?php
 *   $classLevel = 1;
 */
class WithClassExample
{
    /**
     * Carries a function-level example.
     *
     * @example
     *   <?php
     *   $functionLevel = 2;
     */
    public function noop(): void
    {
    }

    /**
     * An example split into paragraphs by a blank line.
     *
     * The " *" line separating the two statements carries no whitespace but the
     * newline, which the decoration stripper used to consume — splicing the two
     * statements onto one line.
     *
     * @example
     *   <?php
     *   $first = 1;
     *
     *   $second = 2;
     */
    public function spaced(): void
    {
    }
}
