<?php

namespace Acme\Fixtures\Types;

use Acme\Fixtures\Types\Marker as Mark;
use Acme\Fixtures\Types\Tagged;

/**
 * Docblock types written against an alias or a group import must resolve to the
 * class that was imported, not to a same-named class in the current namespace.
 */
class Aliasing
{
    /**
     * @return Mark
     */
    public function viaAlias()
    {
        return null;
    }

    /**
     * @return Tagged
     */
    public function viaGroupImport()
    {
        return null;
    }
}
