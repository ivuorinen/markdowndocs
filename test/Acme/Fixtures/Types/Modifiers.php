<?php

declare(strict_types=1);

namespace Acme\Fixtures\Types;

class Modifiers
{
    /**
     * @param string    $first the ordinary one
     * @param string ...$rest  the variadic one
     */
    public function variadic(string $first, string ...$rest): void
    {
    }

    /**
     * @param array &$out filled by the callee
     */
    public function byRef(array &$out): void
    {
    }

    /**
     * @param Marker|null $a documented to match the signature exactly
     * @param int|string  $b a scalar union documented to match
     */
    public function documentedUnions(?Marker $a, int|string $b): void
    {
    }

    /**
     * @return void
     */
    public function contradictedReturnType(): string
    {
        return '';
    }
}
