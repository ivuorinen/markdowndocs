<?php

declare(strict_types=1);

namespace Acme\Fixtures\Links;

/**
 * Refers to the other classes here from inside types: generic parameters,
 * shape values, callable signatures, class constants.
 */
class Nested
{
    /**
     * @return list<Bar>
     */
    public function listOfBar(): array
    {
        return [];
    }

    /**
     * @return array<string, BarBaz>|null
     */
    public function mapOfBarBaz(): ?array
    {
        return null;
    }

    /**
     * @param array{bar: Bar, baz?: BarBaz} $shape a shape
     */
    public function shape(array $shape): void
    {
        unset($shape);
    }

    /**
     * @param callable(Bar): BarBaz $callback a callback
     */
    public function callback(callable $callback): void
    {
        unset($callback);
    }

    /**
     * @return \ArrayObject<int, Bar>
     */
    public function nativeGeneric(): \ArrayObject
    {
        return new \ArrayObject();
    }

    /**
     * @return Bar[]
     */
    public function arrayOfBar(): array
    {
        return [];
    }

    /**
     * @return Bar::FIRST
     */
    public function constant()
    {
        return null;
    }

    /**
     * @param \Countable&Bar $both a countable Bar
     */
    public function nativeInterface($both): void
    {
        unset($both);
    }

    public function namespacedNativeClass(): \Random\Randomizer
    {
        return new \Random\Randomizer();
    }

    public function nativeEnum(): \Random\IntervalBoundary
    {
        return \Random\IntervalBoundary::ClosedOpen;
    }

    /**
     * Returns `\Acme\Fixtures\Links\Bar` objects.
     *
     * @return '\Acme\Fixtures\Links\Bar'|'\Countable'
     */
    public function nameInText()
    {
        return null;
    }

    /**
     * @return \acme\fixtures\links\bar
     */
    public function differentCase()
    {
        return null;
    }

    /**
     * Names a class that is never loaded; see test/stubs/MissingBase.php.
     *
     * @return \Acme\Fixtures\Broken\NotInstalled\MissingBase
     */
    public function notLoaded()
    {
        return null;
    }
}
