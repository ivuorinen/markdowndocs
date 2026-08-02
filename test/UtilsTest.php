<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use PHPDocsMD\Utils;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    public static function nativeTypes(): array
    {
        return array_map(static fn (string $t) => [$t], Utils::$nativeTypes);
    }

    /**
     * @dataProvider nativeTypes
     */
    public function testNativeTypesAreNeverTreatedAsClasses(string $type): void
    {
        $this->assertTrue(Utils::isNativeType($type));
        $this->assertFalse(Utils::isClassReference($type));
        $this->assertEquals($type, Utils::sanitizeDeclaration($type, 'Acme'));
    }

    /**
     * A relative class reference resolves to exactly one namespaced name — not
     * to the raw name *and* the namespaced one.
     */
    public function testSanitizeDeclarationReplacesRatherThanAppends(): void
    {
        $this->assertEquals('\\Acme\\Foo', Utils::sanitizeDeclaration('Foo', 'Acme'));
        $this->assertEquals(
            '\\Acme\\Foo | \\Acme\\Bar',
            Utils::sanitizeDeclaration('Foo|Bar', 'Acme')
        );
    }

    public function testSanitizeDeclarationLeavesAbsoluteReferencesAlone(): void
    {
        $this->assertEquals('\\Other\\Foo', Utils::sanitizeDeclaration('\\Other\\Foo', 'Acme'));
    }

    public function testSanitizeDeclarationDeduplicates(): void
    {
        $this->assertEquals('\\Acme\\Foo', Utils::sanitizeDeclaration('Foo|Foo', 'Acme'));
    }

    public function testGetClassBaseName(): void
    {
        $this->assertEquals('Foo', Utils::getClassBaseName('\\Acme\\NS\\Foo'));
        $this->assertEquals('Foo', Utils::getClassBaseName('Foo'));
    }

    /**
     * "A&B" contains no space, so isClassReference() accepted the whole
     * intersection as one name and the namespace was glued to the front of it,
     * producing the class "\Acme\A&B", which does not exist.
     */
    public function testSanitizeDeclarationResolvesIntersectionMembersIndividually(): void
    {
        $this->assertEquals('\\Acme\\A & \\Acme\\B', Utils::sanitizeDeclaration('A&B', 'Acme'));
        $this->assertEquals(
            '\\Other\\A & \\Acme\\B',
            Utils::sanitizeDeclaration('\\Other\\A&B', 'Acme')
        );
    }

    /**
     * The rendered form has to match what Reflector::formatReflectionType()
     * produces for the same signature, or the two are merged as if they were
     * different types.
     */
    public function testSanitizeDeclarationNormalisesSpacingAroundSeparators(): void
    {
        $this->assertEquals('int | string', Utils::sanitizeDeclaration('int | string', 'Acme'));
        $this->assertEquals('\\Acme\\A & \\Acme\\B', Utils::sanitizeDeclaration('A & B', 'Acme'));
    }

    /**
     * A generic is a base type plus a parameter list. Treating the whole string
     * as one class name produced "\Acme\list<Clause>", and the "<...>" hid the
     * native "array" from isNativeType().
     */
    public function testSanitizeDeclarationDecidesOnTheBaseTypeOfAGeneric(): void
    {
        $this->assertEquals('list<Clause>', Utils::sanitizeDeclaration('list<Clause>', 'Acme'));
        $this->assertEquals('array<string>', Utils::sanitizeDeclaration('array<string>', 'Acme'));
        $this->assertEquals(
            '\\Acme\\Collection<Foo>',
            Utils::sanitizeDeclaration('Collection<Foo>', 'Acme')
        );
        $this->assertEquals(
            '\\Other\\Collection<Foo>',
            Utils::sanitizeDeclaration('\\Other\\Collection<Foo>', 'Acme')
        );
        // "[]" is a different suffix and must keep working
        $this->assertEquals('\\Acme\\Foo[]', Utils::sanitizeDeclaration('Foo[]', 'Acme'));
    }
}
