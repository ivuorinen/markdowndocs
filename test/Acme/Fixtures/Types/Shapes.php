<?php

namespace Acme\Fixtures\Types;

/**
 * Every return-type shape PHP 8.2 can express, none of which may be lost or
 * mangled on the way into the generated table.
 */
class Shapes
{
    public function nativeUnion(): string|int
    {
        return 1;
    }

    public function nullableObject(): ?Marker
    {
        return null;
    }

    /**
     * "?X" and "X|null" are the same type in PHP and must document identically.
     */
    public function explicitNullUnion(): Marker|null
    {
        return null;
    }

    public function nullableScalar(): ?string
    {
        return null;
    }

    public function intersection(): Marker&Tagged
    {
        throw new \RuntimeException('fixture');
    }

    public function plainScalar(): bool
    {
        return true;
    }

    public function nullableParam(?Marker $marker = null, bool $flag = false): void
    {
    }
}
