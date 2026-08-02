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
}
