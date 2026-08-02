<?php

namespace Acme\Fixtures\Kinds;

/**
 * An enum must be discovered by a directory scan.
 */
enum PlainEnum: string
{
    case Yes = 'yes';
    case No = 'no';

    public function label(): string
    {
        return $this->value;
    }
}
