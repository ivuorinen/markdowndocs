<?php

declare(strict_types=1);

namespace Acme\Fixtures\Errors;

/**
 * Shares its short name with the global \Exception, as a package's own
 * exception class commonly does.
 */
class Exception extends \Exception
{
}
