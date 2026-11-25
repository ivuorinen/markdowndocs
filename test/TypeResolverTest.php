<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use PHPDocsMD\TypeResolver;
use PHPDocsMD\Utils;
use PHPStan\PhpDocParser\Parser\ParserException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TypeResolverTest extends TestCase
{
    /**
     * @param array<string, string> $imports
     * @param list<string> $localNames
     */
    private static function resolve(
        string $type,
        array $imports = [],
        string $declaringClass = '',
        array $localNames = []
    ): string {
        $resolver = new TypeResolver('Acme', $imports, $declaringClass, $localNames);

        return TypeResolver::print($resolver->resolve(TypeResolver::parse($type)));
    }

    public static function nativeTypes(): array
    {
        return array_map(static fn (string $t) => [$t], Utils::$nativeTypes);
    }

    #[DataProvider('nativeTypes')]
    public function testNativeTypesAreNeverTreatedAsClasses(string $type): void
    {
        $this->assertSame($type, self::resolve($type));
    }

    /**
     * A relative class reference resolves to exactly one namespaced name — not
     * to the raw name *and* the namespaced one.
     */
    public function testARelativeNameResolvesAgainstTheNamespace(): void
    {
        $this->assertSame('\\Acme\\Foo', self::resolve('Foo'));
        $this->assertSame('\\Acme\\Foo | \\Acme\\Bar', self::resolve('Foo|Bar'));
    }

    /**
     * Text after the type is not ignored: "int $x" is not the type "int".
     */
    public function testParseTakesExactlyOneType(): void
    {
        $this->expectException(ParserException::class);

        TypeResolver::parse('int $x');
    }

    public function testTheGlobalNamespaceAddsOnlyTheLeadingBackslash(): void
    {
        $resolver = new TypeResolver('');

        $this->assertSame('\\Foo', TypeResolver::print($resolver->resolve(TypeResolver::parse('Foo'))));
    }

    /**
     * A name already written in full is not looked up: an import of the same
     * short name has no say over it.
     */
    public function testAFullyQualifiedNameIsLeftAlone(): void
    {
        $this->assertSame('\\Other\\Foo', self::resolve('\\Other\\Foo'));
        $this->assertSame('\\Other\\A', self::resolve('\\Other\\A', ['A' => '\\Vendor\\A']));
    }

    /**
     * The import whose alias is the name's first segment supplies the rest of
     * the namespace, the way PHP resolves a name in code.
     */
    public function testImportsResolveByTheFirstSegment(): void
    {
        $this->assertSame('\\Vendor\\A', self::resolve('A', ['A' => '\\Vendor\\A']));
        $this->assertSame('\\Vendor\\Sub\\Foo', self::resolve('Sub\\Foo', ['Sub' => '\\Vendor\\Sub']));
    }

    /**
     * "namespace\Foo" is PHP's name relative to the current namespace: the
     * keyword stands for the namespace, in any case, and is never an import
     * or a namespace segment of its own.
     */
    public function testANamespaceRelativeNameResolvesAgainstTheNamespace(): void
    {
        $this->assertSame('\\Acme\\Foo', self::resolve('namespace\\Foo'));
        $this->assertSame('\\Acme\\Sub\\Foo', self::resolve('Namespace\\Sub\\Foo', ['Sub' => '\\Vendor\\Sub']));

        $global = new TypeResolver('');
        $this->assertSame('\\Foo', TypeResolver::print($global->resolve(TypeResolver::parse('namespace\\Foo'))));
    }

    /**
     * PHP matches an import alias without regard to case, so "bar" names the
     * imported Bar. The import's own spelling and the rest of the name are
     * kept as written.
     */
    public function testAnImportAliasMatchesWithoutRegardToCase(): void
    {
        $this->assertSame('\\Vendor\\Bar', self::resolve('bar', ['Bar' => '\\Vendor\\Bar']));
        $this->assertSame('\\Vendor\\Sub\\Foo', self::resolve('SUB\\Foo', ['Sub' => '\\Vendor\\Sub']));
    }

    /**
     * "A&B" is two class names. Each resolves on its own, through the imports
     * as much as through the namespace.
     */
    public function testIntersectionMembersResolveIndividually(): void
    {
        $this->assertSame('\\Acme\\A & \\Acme\\B', self::resolve('A&B'));
        $this->assertSame('\\Other\\A & \\Acme\\B', self::resolve('\\Other\\A&B'));
        $this->assertSame('\\Vendor\\A & \\Acme\\B', self::resolve('A&B', ['A' => '\\Vendor\\A']));
    }

    /**
     * Union and intersection separators are spaced however they were written,
     * and a "|" inside a string literal is not a separator.
     */
    public function testSeparatorsAreSpacedOutsideStringLiterals(): void
    {
        $this->assertSame('int | string', self::resolve('int|string'));
        $this->assertSame('int | string', self::resolve('int   |   string'));
        $this->assertSame("'a|b' | 'c&d'", self::resolve("'a|b'|'c&d'"));
    }

    /**
     * A generic is a base type plus parameters, and every class name in it
     * resolves — the base and the parameters alike.
     */
    public function testGenericsResolveTheirBaseAndTheirParameters(): void
    {
        $this->assertSame('list<\\Acme\\Clause>', self::resolve('list<Clause>'));
        $this->assertSame('array<string>', self::resolve('array<string>'));
        $this->assertSame('\\Acme\\Collection<\\Acme\\Foo>', self::resolve('Collection<Foo>'));
        $this->assertSame('\\Other\\Collection<\\Acme\\Foo>', self::resolve('\\Other\\Collection<Foo>'));
        $this->assertSame('\\Acme\\Foo[]', self::resolve('Foo[]'));
    }

    /**
     * "self", "static" and "$this" all name the class the docblock belongs to.
     */
    public function testSelfReferencesNameTheDeclaringClass(): void
    {
        $this->assertSame(
            '\\Acme\\Widget | \\Acme\\Widget | list<\\Acme\\Widget>',
            self::resolve('self|static|list<$this>', declaringClass: 'Acme\\Widget')
        );
    }

    /**
     * Template and type alias names are declared by the docblocks, not by PHP,
     * and are written the way they were declared.
     */
    public function testLocalNamesAreNotClassNames(): void
    {
        $this->assertSame('array<T, Row>', self::resolve('array<T, Row>', localNames: ['T', 'Row']));
    }

    /**
     * Members come out without repeats, compared the way PHP compares class
     * names: without regard to case.
     */
    public function testAUnionHoldsEachMemberOnce(): void
    {
        $type = TypeResolver::parse('\\Acme\\Foo|\\acme\\foo|null|(?int)');

        $this->assertSame(
            '\\Acme\\Foo | null | int',
            TypeResolver::print(TypeResolver::union(TypeResolver::members($type)))
        );
    }

    /**
     * Only names are compared without regard to case. A string literal, a
     * shape key and a class constant's name are case-sensitive, so members
     * that differ in one of them are different types.
     */
    public function testAUnionKeepsMembersThatDifferInCaseSensitiveParts(): void
    {
        $type = TypeResolver::parse(
            "'GET'|'get'|array{Id: int}|array{id: int}|array{Id?: INT}|\\Acme\\Foo::BAR|\\acme\\foo::bar|NULL|null"
        );

        $this->assertSame(
            "'GET' | 'get' | array{Id: int} | array{id: int} | array{Id?: INT}"
                . ' | \\Acme\\Foo::BAR | \\acme\\foo::bar | NULL',
            TypeResolver::print(TypeResolver::union(TypeResolver::members($type)))
        );
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function refinements(): array
    {
        return [
            'the same type'                => ['int', 'int', true],
            'the same class in other case' => ['\\Acme\\Foo', '\\acme\\FOO', true],
            'a generic of array'           => ['array<int, string>', 'array', true],
            'a list of array'              => ['list<int>', 'array', true],
            'a bare list of array'         => ['list', 'array', true],
            'a non-empty array of array'   => ['non-empty-array<int>', 'array', true],
            'an element type of array'     => ['int[]', 'array', true],
            'a shape of array'             => ['array{id: int}', 'array', true],
            'a class string of string'     => ['class-string<\\Foo>', 'string', true],
            'a non-empty string of string' => ['non-empty-string', 'string', true],
            'a string literal of string'   => ["'asc'", 'string', true],
            'a range of int'               => ['int<0, max>', 'int', true],
            'a positive int of int'        => ['positive-int', 'int', true],
            'an int mask of int'           => ['int-mask<1, 2>', 'int', true],
            'an integer literal of int'    => ['1', 'int', true],
            'a float literal of float'     => ['1.5', 'float', true],
            'true of bool'                 => ['true', 'bool', true],
            'false of bool'                => ['false', 'bool', true],
            'a shape of object'            => ['object{id: int}', 'object', true],
            'a signature of callable'      => ['callable(int): void', 'callable', true],
            'a signature of Closure'       => ['\\Closure(int): void', '\\Closure', true],
            'a generic of its class'       => ['\\Acme\\Collection<int>', '\\Acme\\Collection', true],
            'anything of mixed'            => ['\\Acme\\Foo', 'mixed', true],
            'a different scalar'           => ['string', 'int', false],
            'a different class'            => ['\\Acme\\Foo', '\\Acme\\Bar', false],
            'an array of iterable'         => ['array<int>', 'iterable', false],
            'a constant of its class'      => ['\\Acme\\Foo::BAR', '\\Acme\\Foo', false],
            'a key of array'               => ['array-key', 'array', false],
            'a keyword in other case'      => ['INT', 'int', true],
            'a literal in other case'      => ["'GET'", "'get'", false],
            'a shape key in other case'    => ['array{Id: int}', 'array{id: int}', false],
        ];
    }

    /**
     * Whether the first type states the second, or a narrower case of it.
     */
    #[DataProvider('refinements')]
    public function testRefines(string $narrow, string $wide, bool $expected): void
    {
        $this->assertSame($expected, TypeResolver::refines(TypeResolver::parse($narrow), TypeResolver::parse($wide)));
    }
}
