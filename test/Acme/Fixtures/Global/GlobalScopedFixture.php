<?php

declare(strict_types=1);

/**
 * Declared in the global namespace, so no autoloader can find it; tests load
 * it through --bootstrap.
 */
class GlobalScopedFixture
{
    public function ping(): bool
    {
        return true;
    }
}
