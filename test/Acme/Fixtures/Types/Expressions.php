<?php

declare(strict_types=1);

namespace Acme\Fixtures\Types;

/**
 * Docblock types in the PHPStan and Psalm syntax. Class names resolve wherever
 * they appear inside one; shape keys, keywords, template names and type
 * aliases are not class names and stay as written.
 *
 * Every method documents its parameter and its return value with the same
 * type, so both paths are checked against one expectation.
 *
 * @template TKey of array-key
 * @phpstan-type Row array{id: int, marker: Marker}
 * @psalm-type Cell string|int
 * @phpstan-import-type Imported from Shapes as Alias
 */
class Expressions
{
    /**
     * @param array<string, Marker> $value described
     * @return array<string, Marker>
     */
    public function genericWithASpace($value)
    {
        return $value;
    }

    /**
     * @param int | string $value described
     * @return int | string
     */
    public function spacedUnion($value)
    {
        return $value;
    }

    /**
     * @param callable(Marker, int...): void $value described
     * @return callable(Marker, int...): void
     */
    public function callableSignature($value)
    {
        return $value;
    }

    /**
     * @param \Closure(Marker $first, int &$second=): Tagged $value described
     * @return \Closure(Marker $first, int &$second=): Tagged
     */
    public function closureSignature($value)
    {
        return $value;
    }

    /**
     * @param array{id: int, marker?: Marker, 0: Tagged} $value described
     * @return array{id: int, marker?: Marker, 0: Tagged}
     */
    public function arrayShape($value)
    {
        return $value;
    }

    /**
     * @param list{int, Marker} $value described
     * @return list{int, Marker}
     */
    public function listShape($value)
    {
        return $value;
    }

    /**
     * @param array{id: int, ...<string, Marker>} $value described
     * @return array{id: int, ...<string, Marker>}
     */
    public function unsealedShape($value)
    {
        return $value;
    }

    /**
     * @param object{marker: Marker, count?: int} $value described
     * @return object{marker: Marker, count?: int}
     */
    public function objectShape($value)
    {
        return $value;
    }

    /**
     * @param 'asc'|'desc'|1|2.5 $value described
     * @return 'asc'|'desc'|1|2.5
     */
    public function literals($value)
    {
        return $value;
    }

    /**
     * @param Marker::FIRST|Marker::* $value described
     * @return Marker::FIRST|Marker::*
     */
    public function classConstants($value)
    {
        return $value;
    }

    /**
     * @param int<0, max>|int<min, -1> $value described
     * @return int<0, max>|int<min, -1>
     */
    public function integerRanges($value)
    {
        return $value;
    }

    /**
     * @param class-string<Marker> $value described
     * @return class-string<Marker>
     */
    public function classString($value)
    {
        return $value;
    }

    /**
     * @param ?Marker $value described
     * @return ?Marker
     */
    public function nullable($value)
    {
        return $value;
    }

    /**
     * @param (Marker&Tagged)|null $value described
     * @return (Marker&Tagged)|null
     */
    public function disjunctiveNormalForm($value)
    {
        return $value;
    }

    /**
     * @param Marker[][] $value described
     * @return Marker[][]
     */
    public function nestedArrays($value)
    {
        return $value;
    }

    /**
     * @param (int|Marker)[] $value described
     * @return (int|Marker)[]
     */
    public function arrayOfAUnion($value)
    {
        return $value;
    }

    /**
     * @param Collection<covariant Marker> $value described
     * @return Collection<covariant Marker>
     */
    public function variance($value)
    {
        return $value;
    }

    /**
     * Hyphenated pseudo-types: no PHP class name can contain a hyphen.
     *
     * @param non-empty-literal-string|truthy-string|pure-callable|non-zero-int $value described
     * @return non-empty-literal-string|truthy-string|pure-callable|non-zero-int
     */
    public function pseudoTypes($value)
    {
        return $value;
    }

    /**
     * @param list<static>|self[] $value described
     * @return list<static>|self[]
     */
    public function selfReferences($value)
    {
        return $value;
    }

    /**
     * @template T of Marker
     * @param T $value described
     * @return T
     */
    public function methodTemplate($value)
    {
        return $value;
    }

    /**
     * @param array<TKey, Marker> $value described
     * @return array<TKey, Marker>
     */
    public function classTemplate($value)
    {
        return $value;
    }

    /**
     * @param Row|Cell|Alias $value described
     * @return Row|Cell|Alias
     */
    public function typeAliases($value)
    {
        return $value;
    }

    /**
     * @param ($value is int ? Marker : Tagged) $value described
     * @return ($value is int ? Marker : Tagged)
     */
    public function conditionalOnAParameter($value)
    {
        return $value;
    }

    /**
     * @template T
     * @param (T is Marker ? list<T> : Tagged) $value described
     * @return (T is Marker ? list<T> : Tagged)
     */
    public function conditionalOnATemplate($value)
    {
        return $value;
    }

    /**
     * @param Row['marker'] $value described
     * @return Row['marker']
     */
    public function offsetAccess($value)
    {
        return $value;
    }

    /**
     * @param callable<T of Marker>(T): T $value described
     * @return callable<T of Marker>(T): T
     */
    public function callableTemplate($value)
    {
        return $value;
    }

    /**
     * @param key-of<Row>|value-of<Row> $value described
     * @return key-of<Row>|value-of<Row>
     */
    public function keyAndValueOf($value)
    {
        return $value;
    }

    /**
     * @param int-mask-of<Marker::*> $value described
     * @return int-mask-of<Marker::*>
     */
    public function intMaskOf($value)
    {
        return $value;
    }
}
