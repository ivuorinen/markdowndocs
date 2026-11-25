<?php

declare(strict_types=1);

namespace Acme\Fixtures\Broken;

/**
 * Extends a class that is deliberately not installed.
 *
 * Loading this file fatals from inside the autoloader, which is exactly what a
 * class with an unsatisfied optional dependency does in a real project. The
 * scan must skip it and keep documenting its siblings.
 *
 * The parent is declared for static analysis in test/stubs/MissingBase.php,
 * which is outside the autoload path, so at runtime it is still missing.
 */
class UnloadableChild extends \Acme\Fixtures\Broken\NotInstalled\MissingBase
{
}
