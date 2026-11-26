<?php

declare(strict_types=1);

namespace Acme\Fixtures\AliasLinks;

/**
 * Declares its types with the alias, so the type cells name TargetAlias.
 */
class TakesAlias
{
    public function take(TargetAlias $value): TargetAlias
    {
        return $value;
    }
}
