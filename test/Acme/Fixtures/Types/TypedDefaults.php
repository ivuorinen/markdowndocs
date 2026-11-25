<?php

declare(strict_types=1);

namespace Acme\Fixtures\Types;

/**
 * A constant of this namespace, for a default that names one.
 */
const DEFAULT_LIMIT = 10;

class TypedDefaults
{
    public const SEPARATOR = ', ';

    /**
     * @param int   $count how many
     * @param float $ratio how much
     */
    public function scale(int $count = 5, float $ratio = 0.5): void
    {
        unset($count, $ratio);
    }

    /**
     * The defaults are an array and a string, but the declared and documented
     * types agree on something wider, and that is the type.
     *
     * @param iterable $items anything iterable
     * @param mixed    $value anything at all
     */
    public function accept(iterable $items = [], mixed $value = 'none'): void
    {
        unset($items, $value);
    }

    /**
     * Defaults written as expressions: each is documented as written, not as
     * the value it evaluates to.
     */
    public function declaredExpressions(
        int $flags = SORT_STRING,
        string $eol = \PHP_EOL,
        int $limit = DEFAULT_LIMIT,
        string $separator = self::SEPARATOR,
        string $format = \DateTimeInterface::ATOM,
        array $map = ['a' => 1],
        \ArrayObject $items = new \ArrayObject([1]),
        string $quote = "it's",
        string $lines = "a\nb"
    ): void {
        unset($flags, $eol, $limit, $separator, $format, $map, $items, $quote, $lines);
    }
}
