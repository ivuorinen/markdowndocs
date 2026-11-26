<?php

declare(strict_types=1);

namespace Acme\Fixtures\AliasLinks;

// A second name for Target, the way a package keeps an old name resolvable.
// The declaration below is for static analysis only; at runtime the name is
// the alias class_alias() creates, and reflecting it yields Target.
if (false) {
    class TargetAlias extends Target
    {
    }
}

class_alias(Target::class, 'Acme\Fixtures\AliasLinks\TargetAlias');
