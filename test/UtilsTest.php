<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use PHPDocsMD\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    public static function nativeTypes(): array
    {
        return array_map(static fn (string $t) => [$t], Utils::$nativeTypes);
    }

    #[DataProvider('nativeTypes')]
    public function testNativeTypesAreNeverTreatedAsClasses(string $type): void
    {
        $this->assertTrue(Utils::isNativeType($type));
        $this->assertFalse(Utils::isClassReference($type));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hyphenatedPseudoTypes(): array
    {
        $names = [
            'array-key', 'class-string', 'non-empty-list', 'non-empty-array', 'non-empty-string',
            'numeric-string', 'positive-int', 'negative-int', 'non-positive-int', 'non-negative-int',
            'literal-int', 'int-mask', 'int-mask-of', 'int-range', 'literal-string', 'non-falsy-string',
            'lowercase-string', 'non-empty-lowercase-string', 'interface-string', 'trait-string',
            'enum-string', 'callable-string', 'callable-array', 'associative-array', 'class-string-map',
            'properties-of', 'key-of', 'value-of',
            // Never listed by name; the hyphen alone settles them.
            'non-empty-literal-string', 'truthy-string', 'uppercase-string', 'non-zero-int',
            'pure-callable', 'callable-object', 'open-resource', 'closed-resource', 'never-return',
            'non-empty-mixed',
        ];

        return array_combine($names, array_map(static fn (string $name) => [$name], $names));
    }

    /**
     * No PHP class name can contain a hyphen, so every hyphenated name is a
     * pseudo-type — including the ones PHPStan and Psalm add after this list
     * was written.
     */
    #[DataProvider('hyphenatedPseudoTypes')]
    public function testHyphenatedPseudoTypesAreNeverTreatedAsClasses(string $type): void
    {
        $this->assertTrue(Utils::isNativeType($type));
        $this->assertFalse(Utils::isClassReference($type));
    }

    public function testGetClassBaseName(): void
    {
        $this->assertEquals('Foo', Utils::getClassBaseName('\\Acme\\NS\\Foo'));
        $this->assertEquals('Foo', Utils::getClassBaseName('Foo'));
    }
}
