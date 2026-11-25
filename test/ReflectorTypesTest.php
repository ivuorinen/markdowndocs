<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

class ReflectorTypesTest extends ReflectorTestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function importedParameterTypes(): array
    {
        return [
            'untyped, documented with an import'    => ['untypedParam', '\\Acme\\Fixtures\\Links\\Bar'],
            'nullable, documented with an alias'    => ['nullableAliasParam', '\\Acme\\Fixtures\\Links\\BarBaz | null'],
            'array, documented as a list of import' => ['listParam', '\\Acme\\Fixtures\\Links\\Bar[]'],
            // The docblock's short name resolves to the current namespace, which
            // is wrong; the signature names the class in full and wins.
            'signature names the class in full'     => ['fullyQualifiedSignature', '\\Acme\\Fixtures\\Links\\BarBaz'],
        ];
    }

    /**
     * Docblock class names resolve the way PHP resolves names in code: through
     * the import whose alias matches the first segment, otherwise against the
     * current namespace.
     *
     * @throws \ReflectionException
     */
    #[DataProvider('importedParameterTypes')]
    public function testParameterTypesResolveThroughImports(string $method, string $expected): void
    {
        $this->assertSame($expected, $this->functionOf('Acme\\Fixtures\\Types\\ImportedNames', $method)
            ->getParams()[0]->getType());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function importedReturnTypes(): array
    {
        return [
            'union with an import'           => ['unionReturn', '\\Acme\\Fixtures\\Links\\Bar | null'],
            'qualified via a namespace import' => ['namespaceImportReturn', '\\Acme\\Fixtures\\Links\\BarBaz'],
            'import shadowing a global class'  => ['shadowsGlobalClassReturn', '\\Acme\\Fixtures\\Errors\\Exception'],
        ];
    }

    /**
     * @throws \ReflectionException
     */
    #[DataProvider('importedReturnTypes')]
    public function testReturnTypesResolveThroughImports(string $method, string $expected): void
    {
        $this->assertSame(
            $expected,
            $this->functionOf('Acme\\Fixtures\\Types\\ImportedNames', $method)->getReturnType()
        );
    }

    /**
     * A declared "self" is the declaring class, written out like any other
     * class reference.
     *
     * @throws \ReflectionException
     */
    public function testDeclaredSelfIsTheDeclaringClass(): void
    {
        $function = $this->functionOf('Acme\\Fixtures\\Types\\SelfTyped', 'merge');

        $this->assertSame('\\Acme\\Fixtures\\Types\\SelfTyped', $function->getParams()[0]->getType());
        $this->assertSame('\\Acme\\Fixtures\\Types\\SelfTyped', $function->getReturnType());
    }

    /**
     * A declared "static" names the declaring class, as "@return static" does;
     * left as the keyword, a documented "static" no longer matched it.
     *
     * @throws \ReflectionException
     */
    public function testDeclaredStaticIsTheDeclaringClass(): void
    {
        foreach (['fluent', 'documentedFluent'] as $method) {
            $this->assertSame(
                '\\Acme\\Fixtures\\Types\\SelfTyped',
                $this->functionOf('Acme\\Fixtures\\Types\\SelfTyped', $method)->getReturnType(),
                $method
            );
        }
    }

    /**
     * @throws \ReflectionException
     */
    public function testNumericDefaultsKeepTheirDeclaredType(): void
    {
        [$count, $ratio] = $this->functionOf('Acme\\Fixtures\\Types\\TypedDefaults', 'scale')->getParams();

        $this->assertSame('int', $count->getType());
        $this->assertSame('5', $count->getDefault());
        $this->assertSame('float', $ratio->getType());
        $this->assertSame('0.5', $ratio->getDefault());
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function declaredDefaults(): array
    {
        return [
            'a global constant written unqualified' => [0, 'SORT_STRING'],
            'a global constant written in full'     => [1, 'PHP_EOL'],
            'a constant of this namespace'          => [2, '\\Acme\\Fixtures\\Types\\DEFAULT_LIMIT'],
            'a constant of this class'              => [3, 'self::SEPARATOR'],
            'a constant of another class'           => [4, '\\DateTimeInterface::ATOM'],
            'a non-empty array'                     => [5, "`['a' => 1]`"],
            'a new expression'                      => [6, '`new \\ArrayObject([0 => 1])`'],
            'a string holding a quote'              => [7, "`'it\\'s'`"],
            'a string holding a newline'            => [8, '`"a\\nb"`'],
        ];
    }

    /**
     * A default is documented as the signature declares it. Rendering the
     * evaluated value printed 2 for SORT_STRING, [] for every array, a
     * var_export() dump for an object, and a literal line break for "\n".
     *
     * @throws \ReflectionException
     */
    #[DataProvider('declaredDefaults')]
    public function testDefaultsRenderAsDeclared(int $position, string $expected): void
    {
        $params = $this->functionOf('Acme\\Fixtures\\Types\\TypedDefaults', 'declaredExpressions')->getParams();

        $this->assertSame($expected, $params[$position]->getDefault());
    }

    /**
     * When the declaration and the docblock agree, the default's own type —
     * array for [], string for 'none' — must not be merged in.
     *
     * @throws \ReflectionException
     */
    public function testAgreeingTypesAreNotWidenedByTheDefault(): void
    {
        [$items, $value] = $this->functionOf('Acme\\Fixtures\\Types\\TypedDefaults', 'accept')->getParams();

        $this->assertSame('iterable', $items->getType());
        $this->assertSame('mixed', $value->getType());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function typeExpressions(): array
    {
        $ns = '\\Acme\\Fixtures\\Types\\';

        return [
            'a generic with a space'       => ['genericWithASpace', "array<string, {$ns}Marker>"],
            'a union written with spaces'  => ['spacedUnion', 'int | string'],
            'a callable signature'         => ['callableSignature', "callable({$ns}Marker, int ...): void"],
            'a closure signature'          => [
                'closureSignature',
                "\\Closure({$ns}Marker \$first, int &\$second=): {$ns}Tagged",
            ],
            'an array shape'               => ['arrayShape', "array{id: int, marker?: {$ns}Marker, 0: {$ns}Tagged}"],
            'a list shape'                 => ['listShape', "list{int, {$ns}Marker}"],
            'an unsealed shape'            => ['unsealedShape', "array{id: int, ...<string, {$ns}Marker>}"],
            'an object shape'              => ['objectShape', "object{marker: {$ns}Marker, count?: int}"],
            'literals'                     => ['literals', "'asc' | 'desc' | 1 | 2.5"],
            'class constants'              => ['classConstants', "{$ns}Marker::FIRST | {$ns}Marker::*"],
            'integer ranges'               => ['integerRanges', 'int<0, max> | int<min, -1>'],
            'a class string'               => ['classString', "class-string<{$ns}Marker>"],
            // "?X" is "X|null", which is how a declared "?X" renders too
            'a nullable class'             => ['nullable', "{$ns}Marker | null"],
            'disjunctive normal form'      => ['disjunctiveNormalForm', "({$ns}Marker & {$ns}Tagged) | null"],
            'nested arrays'                => ['nestedArrays', "{$ns}Marker[][]"],
            'an array of a union'          => ['arrayOfAUnion', "(int | {$ns}Marker)[]"],
            'variance'                     => ['variance', "{$ns}Collection<covariant {$ns}Marker>"],
            'hyphenated pseudo-types'      => [
                'pseudoTypes',
                'non-empty-literal-string | truthy-string | pure-callable | non-zero-int',
            ],
            'self references'              => ['selfReferences', "list<{$ns}Expressions> | {$ns}Expressions[]"],
            'a method template'            => ['methodTemplate', 'T'],
            'a class template'             => ['classTemplate', "array<TKey, {$ns}Marker>"],
            'type aliases'                 => ['typeAliases', 'Row | Cell | Alias'],
            'a conditional on a parameter' => [
                'conditionalOnAParameter',
                "(\$value is int ? {$ns}Marker : {$ns}Tagged)",
            ],
            'a conditional on a template'  => [
                'conditionalOnATemplate',
                "(T is {$ns}Marker ? list<T> : {$ns}Tagged)",
            ],
            'offset access'                => ['offsetAccess', "Row['marker']"],
            'a callable template'          => ['callableTemplate', "callable<T of {$ns}Marker>(T): T"],
            'key-of and value-of'          => ['keyAndValueOf', 'key-of<Row> | value-of<Row>'],
            'int-mask-of'                  => ['intMaskOf', "int-mask-of<{$ns}Marker::*>"],
        ];
    }

    /**
     * A type that contains spaces is still one type: the parameter keeps its
     * name and description, and class names resolve wherever they sit in it.
     * Splitting the tag on spaces lost the parameter documentation entirely
     * and cut return types off at the first space.
     *
     * @throws \ReflectionException
     */
    #[DataProvider('typeExpressions')]
    public function testDocblockTypeExpressionsRenderIntact(string $method, string $expected): void
    {
        $function = $this->functionOf('Acme\\Fixtures\\Types\\Expressions', $method);
        $param = $function->getParams()[0];

        $this->assertSame($expected, $param->getType());
        $this->assertSame('described', $param->getDescription());
        $this->assertSame($expected, $function->getReturnType());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refinedParameterTypes(): array
    {
        $ns = '\\Acme\\Fixtures\\Types\\';

        return [
            'a generic array'                    => ['genericArrayParam', "array<int, {$ns}Marker>"],
            'a nullable list'                    => ['nullableListParam', "list<{$ns}Marker> | null"],
            'a member the declaration rules out' => ['contradictedParam', 'string'],
            'a template for its declared bound'  => ['templateParam', 'T'],
            'a class string'                     => ['classStringParam', "class-string<{$ns}Marker>"],
            'a non-empty string'                 => ['nonEmptyStringParam', 'non-empty-string'],
            'an integer range'                   => ['integerRangeParam', 'int<1, max>'],
            'true for bool'                      => ['trueParam', 'true'],
            'anything for mixed'                 => ['mixedParam', 'list<int>'],
            'a callable signature'               => ['callableParam', 'callable(int): void'],
            'a closure signature'                => ['closureParam', '\\Closure(int): void'],
            'an array shape'                     => ['shapeParam', 'array{id: int}'],
            'an untyped list with [] default'    => ['untypedWithAnArrayDefault', "list<{$ns}Marker>"],
            'an untyped class with a string default' => ['untypedWithAStringDefault', "string | {$ns}Marker"],
            'a typeless @param'                  => ['typelessParam', 'int'],
            '@phpstan-param over @param'         => ['phpstanParam', "list<{$ns}Marker>"],
            '@psalm-param over @param'           => ['psalmParam', "list<{$ns}Tagged>"],
        ];
    }

    /**
     * A docblock member that refines a declared one — a generic of "array", a
     * "class-string" of "string" — is the same type stated precisely. Listing
     * both rendered "array<int, Marker> | array".
     *
     * @throws \ReflectionException
     */
    #[DataProvider('refinedParameterTypes')]
    public function testDocblockTypesRefineTheDeclaredParameterType(string $method, string $expected): void
    {
        $param = $this->functionOf('Acme\\Fixtures\\Types\\Refinements', $method)->getParams()[0];

        $this->assertSame($expected, $param->getType());
        $this->assertSame('described', $param->getDescription());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refinedReturnTypes(): array
    {
        $ns = '\\Acme\\Fixtures\\Types\\';

        return [
            'a generic array'                     => ['genericArrayReturn', "array<string, {$ns}Marker>"],
            'a member the declaration rules out'  => ['narrowedReturn', "list<{$ns}Marker>"],
            'a nullable list'                     => ['nullableListReturn', "non-empty-list<{$ns}Marker> | null"],
            '@phpstan-return over the others'     => ['prefixedReturn', "list<{$ns}Marker>"],
        ];
    }

    /**
     * The declared return type is what the engine enforces; the docblock can
     * narrow each of its members but not add to them.
     *
     * @throws \ReflectionException
     */
    #[DataProvider('refinedReturnTypes')]
    public function testDocblockTypesRefineTheDeclaredReturnType(string $method, string $expected): void
    {
        $this->assertSame(
            $expected,
            $this->functionOf('Acme\\Fixtures\\Types\\Refinements', $method)->getReturnType()
        );
    }
}
