<?php

declare(strict_types=1);

namespace Acme\Fixtures\Aliased;

require_once __DIR__ . '/Original.php';

// The shape a package leaves behind when it renames a class: a file whose only
// job is to keep the old name resolvable. Reflection reports the *original*
// name for it, so scanning the directory used to document Original twice, under
// one anchor id.
if (false) {
    /**
     * @deprecated use Original instead.
     */
    class Renamed extends Original
    {
    }
}

class_alias(Original::class, Renamed::class);
