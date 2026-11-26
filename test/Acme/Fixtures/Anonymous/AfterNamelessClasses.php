<?php

declare(strict_types=1);

namespace Acme\Fixtures\Anonymous;

// "::class" and an anonymous class are T_CLASS tokens too. Ahead of the
// declaration below, neither may be read as one: taking either for the type
// this file declares would hide AfterNamelessClasses from the document.
const DEFAULT_STORAGE = \ArrayObject::class;

function defaultStorage(): \ArrayObject
{
    return new class extends \ArrayObject
    {
    };
}

class AfterNamelessClasses
{
    public function storage(): \ArrayObject
    {
        return defaultStorage();
    }
}
