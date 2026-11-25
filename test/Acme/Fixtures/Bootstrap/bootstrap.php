<?php

declare(strict_types=1);

namespace Acme\Fixtures\Bootstrap;

/**
 * Defined only by this bootstrap file. The autoloader would look for
 * OnlyViaBootstrap.php, which does not exist, so the class can be documented
 * only after --bootstrap has loaded this file.
 */
class OnlyViaBootstrap
{
    public function loaded(): bool
    {
        return true;
    }
}
