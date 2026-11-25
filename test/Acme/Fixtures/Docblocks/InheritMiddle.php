<?php

declare(strict_types=1);

namespace Acme\Fixtures\Docblocks;

/**
 * Sits between InheritBase and InheritGrandchild without declaring work().
 */
abstract class InheritMiddle extends InheritBase
{
}
