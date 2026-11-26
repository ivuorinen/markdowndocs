<?php

declare(strict_types=1);

namespace Acme\Fixtures\Broken\NotInstalled;

/**
 * Declared for static analysis only.
 *
 * UnloadableChild extends this class to stand in for an optional dependency
 * that is not installed, and Links\Nested names it as a class that is never
 * loaded. Analysers read the whole workspace, so they find it here. The
 * autoloader only looks under the PSR-4 path, which for this name is
 * test/Acme/Fixtures/Broken/NotInstalled/. It never finds this file, so loading
 * UnloadableChild still fails at runtime. Do not move this file onto that path.
 */
class MissingBase
{
}
