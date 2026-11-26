<?php

declare(strict_types=1);

namespace Acme\Fixtures\Docblocks;

class Continuations
{
    /**
     * Describes the return value at length.
     *
     * @return string the rendered name, which needs
     *                more than one line to explain
     */
    public function wrappedReturn(): string
    {
        return '';
    }

    /**
     * Lists a parameter, then mistypes the next tag.
     *
     * @param string $name the name
     * @params string $stray
     */
    public function strayAfterParam(string $name): void
    {
        unset($name);
    }

    /**
     * Documents a parameter type but forgets its name.
     *
     * @param string
     *        a wrapped description with no parameter to belong to
     */
    public function unnamedParam(string $name): void
    {
        unset($name);
    }

    /**
     * Quotes a phrase that wraps: "the first half and
     * the second half" read as one.
     *
     * @param string $name a "name that also
     *                     wraps" inside its quotes
     */
    public function quoteWrapsALine(string $name): void
    {
        unset($name);
    }

    /**
     * Counts things.
     *
     * @param array<int $items the items to count
     * @return int<0, the count
     */
    public function malformedTags($items)
    {
        unset($items);

        return 0;
    }

    /**
     * Shows two examples.
     *
     * @example
     *   $first = 1;
     * @example
     *   $second = 2;
     */
    public function twoExamples(): void
    {
    }

    /**
     * Fits a 12" pipe.
     *
     * @param string $name the "inner" diameter
     * @return string
     */
    public function strayQuote(string $name): string
    {
        return $name;
    }
}
