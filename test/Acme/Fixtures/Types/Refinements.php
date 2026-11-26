<?php

declare(strict_types=1);

namespace Acme\Fixtures\Types;

/**
 * Docblock types that narrow what the signature declares. A docblock member
 * that refines a declared one says the same thing more precisely, so the two
 * are not listed side by side.
 */
class Refinements
{
    /**
     * @param array<int, Marker> $value described
     */
    public function genericArrayParam(array $value): void
    {
        unset($value);
    }

    /**
     * @param list<Marker>|null $value described
     */
    public function nullableListParam(?array $value): void
    {
        unset($value);
    }

    /**
     * The declaration rules out the documented null.
     *
     * @param string|null $value described
     */
    public function contradictedParam(string $value): void
    {
        unset($value);
    }

    /**
     * A template stands in for its bound; the declaration is that bound.
     *
     * @template T of object
     *
     * @param T $value described
     */
    public function templateParam(object $value): void
    {
        unset($value);
    }

    /**
     * The template stands in for the declared object, not for the null.
     *
     * @template T of object
     *
     * @param T $value described
     */
    public function nullableTemplateParam(?object $value): void
    {
        unset($value);
    }

    /**
     * @param Marker $value described
     */
    public function classForObjectParam(object $value): void
    {
        unset($value);
    }

    /**
     * @return Marker
     */
    public function classForObjectReturn(): object
    {
        return new Marker();
    }

    /**
     * @param class-string<Marker> $value described
     */
    public function classStringParam(string $value): void
    {
        unset($value);
    }

    /**
     * @param non-empty-string $value described
     */
    public function nonEmptyStringParam(string $value): void
    {
        unset($value);
    }

    /**
     * @param int<1, max> $value described
     */
    public function integerRangeParam(int $value): void
    {
        unset($value);
    }

    /**
     * @param true $value described
     */
    public function trueParam(bool $value): void
    {
        unset($value);
    }

    /**
     * @param list<int> $value described
     */
    public function mixedParam(mixed $value): void
    {
        unset($value);
    }

    /**
     * @param callable(int): void $value described
     */
    public function callableParam(callable $value): void
    {
        unset($value);
    }

    /**
     * @param \Closure(int): void $value described
     */
    public function closureParam(\Closure $value): void
    {
        unset($value);
    }

    /**
     * @param array{id: int} $value described
     */
    public function shapeParam(array $value): void
    {
        unset($value);
    }

    /**
     * The empty-array default is an array, which the documented list already
     * is; it adds nothing.
     *
     * @param list<Marker> $value described
     */
    public function untypedWithAnArrayDefault($value = []): void
    {
        unset($value);
    }

    /**
     * Without a declaration the default is the only other evidence of what
     * the parameter accepts, and a string is not a Marker.
     *
     * @param Marker $value described
     */
    public function untypedWithAStringDefault($value = 'none'): void
    {
        unset($value);
    }

    /**
     * A typeless @param describes the parameter without claiming a type.
     *
     * @param $value described
     */
    public function typelessParam(int $value): void
    {
        unset($value);
    }

    /**
     * @param array $value described
     * @phpstan-param list<Marker> $value
     */
    public function phpstanParam(array $value): void
    {
        unset($value);
    }

    /**
     * @param array $value described
     * @psalm-param list<Tagged> $value
     */
    public function psalmParam(array $value): void
    {
        unset($value);
    }

    /**
     * @return array<string, Marker>
     */
    public function genericArrayReturn(): array
    {
        return [];
    }

    /**
     * The declaration rules out the string member, so it is not shown.
     *
     * @return list<Marker>|string
     */
    public function narrowedReturn(): array
    {
        return [];
    }

    /**
     * @return non-empty-list<Marker>|null
     */
    public function nullableListReturn(): ?array
    {
        return null;
    }

    /**
     * @return array
     * @psalm-return list<Tagged>
     * @phpstan-return list<Marker>
     */
    public function prefixedReturn(): array
    {
        return [];
    }
}
