<?php

declare(strict_types=1);

/** @noinspection ClassConstantCanBeUsedInspection */

namespace PHPDocsMD\Tests;

use ErrorException;
use InvalidArgumentException;
use PHPDocsMD\Entities\ClassEntity;
use PHPDocsMD\Entities\FunctionEntity;
use PHPDocsMD\Reflections\Reflector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionParameter;

class ReflectorTest extends TestCase
{

    /**
     * @var \PHPDocsMD\Reflections\Reflector
     */
    private Reflector $reflector;

    /**
     * @var \PHPDocsMD\Entities\ClassEntity
     */
    private ClassEntity $class;

    /**
     * A name that resolves to no class, interface or trait is rejected with a
     * ReflectionException rather than reflected or skipped.
     */
    public function testUnknownClassNameIsRejected(): void
    {
        $this->expectException(\ReflectionException::class);
        $this->expectExceptionMessage('Class "Acme\\No\\Such\\Thing" does not exist');

        (new Reflector('Acme\\No\\Such\\Thing'))->getClassEntity();
    }

    /**
     * @throws \ReflectionException
     */
    public function testClass(): void
    {
        $this->assertEquals('\\Acme\\ExampleClass', $this->class->getName());
        $this->assertEquals('This is a description of this class', $this->class->getDescription());
        $this->assertEquals('Class: \\Acme\\ExampleClass (abstract)', $this->class->generateTitle());
        $this->assertEquals('class-acme-exampleclass-abstract', $this->class->generateAnchor());
        $this->assertFalse($this->class->isDeprecated());
        $this->assertFalse($this->class->hasIgnoreTag());

        $refl  = new Reflector('Acme\\ExampleClassDepr');
        $class = $refl->getClassEntity();
        $this->assertTrue($class->isDeprecated());
        $this->assertEquals('This one is deprecated Lorem te ipsum', $class->getDeprecationMessage());
        $this->assertFalse($class->hasIgnoreTag());

        $refl  = new Reflector('Acme\\ExampleInterface');
        $class = $refl->getClassEntity();
        $this->assertTrue($class->isInterface());
        $this->assertTrue($class->hasIgnoreTag());
    }

    public function testFunctions(): void
    {
        $functions = $this->class->getFunctions();

        $this->assertNotEmpty($functions);

        $this->assertEquals('Description of a*a', $functions[0]->getDescription());
        $this->assertFalse($functions[0]->isDeprecated());
        $this->assertEquals('funcA', $functions[0]->getName());
        $this->assertEquals('void', $functions[0]->getReturnType());
        $this->assertEquals('public', $functions[0]->getVisibility());

        $this->assertEquals('Description of b', $functions[1]->getDescription());
        $this->assertFalse($functions[1]->isDeprecated());
        $this->assertEquals('funcB', $functions[1]->getName());
        $this->assertEquals('void', $functions[1]->getReturnType());
        $this->assertEquals('public', $functions[1]->getVisibility());

        $this->assertEquals('', $functions[2]->getDescription());
        $this->assertEquals('funcD', $functions[2]->getName());
        $this->assertEquals('void', $functions[2]->getReturnType());
        $this->assertEquals('public', $functions[2]->getVisibility());
        $this->assertFalse($functions[2]->isDeprecated());

        // These function does not declare return type but the return
        // type should be guessable
        $this->assertEquals('mixed', $functions[3]->getReturnType());
        $this->assertEquals('bool', $functions[4]->getReturnType());
        $this->assertEquals('bool', $functions[5]->getReturnType());
        $this->assertTrue($functions[5]->isAbstract());
        $this->assertTrue($this->class->isAbstract());

        // Protected function have been put last
        $this->assertEquals('Description of c', $functions[6]->getDescription());
        $this->assertTrue($functions[6]->isDeprecated());
        $this->assertEquals('This one is deprecated', $functions[6]->getDeprecationMessage());
        $this->assertEquals('funcC', $functions[6]->getName());
        $this->assertEquals('\\Acme\\ExampleClass', $functions[6]->getReturnType());
        $this->assertEquals('protected', $functions[6]->getVisibility());

        // The @ignore-tagged function must not be included. assertCount pins the
        // list length; empty() passed for any falsy value and for a longer list.
        $this->assertCount(7, $functions);
    }

    /**
     * @throws \ReflectionException
     */
    public function testStaticFunc(): void
    {
        $reflector = new Reflector('Acme\\ClassWithStaticFunc');
        $functions = $reflector->getClassEntity()->getFunctions();
        $this->assertNotEmpty($functions);
        $this->assertEquals('', $functions[0]->getDescription());
        $this->assertFalse($functions[0]->isDeprecated());
        $this->assertTrue($functions[0]->isStatic());
        $this->assertEquals('', $functions[0]->getDeprecationMessage());
        $this->assertEquals('someStaticFunc', $functions[0]->getName());
        $this->assertEquals('public', $functions[0]->getVisibility());
        $this->assertEquals('float', $functions[0]->getReturnType());
    }

    /**
     * @throws \ReflectionException
     */
    public function testParams(): void
    {
        $paramA = new ReflectionParameter(['Acme\\ExampleClass', 'funcD'], 2);
        $paramB = new ReflectionParameter(['Acme\\ExampleClass', 'funcD'], 3);
        $paramC = new ReflectionParameter(['Acme\\ExampleClass', 'funcD'], 0);

        $typeA = Reflector::getParamType($paramA);
        $typeB = Reflector::getParamType($paramB);
        $typeC = Reflector::getParamType($paramC);

        // funcD declares both as "?X $p = null", so null is part of the type.
        // Spaced like every other rendered union.
        $this->assertEmpty($typeC);
        $this->assertEquals('\\stdClass | null', $typeB);
        $this->assertEquals('\\Acme\\ExampleInterface | null', $typeA);

        $functions = $this->class->getFunctions();

        $this->assertTrue($functions[2]->hasParams());
        $this->assertFalse($functions[5]->hasParams());

        // funcB documents "@param int $arg" — the declared type must survive
        $params = $functions[1]->getParams();
        $this->assertEquals('int', $params[0]->getType());

        $params = $functions[2]->getParams();
        $this->assertEquals(4, count($params));
        $this->assertFalse($params[0]->hasDefault());
        $this->assertEquals('$arg', $params[0]->getName());
        $this->assertEquals('mixed', $params[0]->getType());
        $this->assertEquals('[]', $params[1]->getDefault());
        $this->assertEquals('$arr', $params[1]->getName());
        $this->assertEquals('array', $params[1]->getType());
        $this->assertEquals('null', $params[2]->getDefault());
        $this->assertEquals('$depr', $params[2]->getName());
        $this->assertEquals('\\Acme\\ExampleInterface | null', $params[2]->getType());
    }

    /**
     * getName() drops the "?" and PHP collapses "X|null" onto the same nullable
     * named type, so both spellings have to be restored — and must agree.
     *
     * @throws \ReflectionException
     */
    public function testReturnTypeShapesSurviveIntact(): void
    {
        $reflector = new Reflector('Acme\\Fixtures\\Types\\Shapes');
        $byName    = [];
        foreach ($reflector->getClassEntity()->getFunctions() as $function) {
            $byName[$function->getName()] = $function->getReturnType();
        }

        // A native union must not have its first member turned into a class ref
        $this->assertEquals('string | int', $byName['nativeUnion']);

        $this->assertEquals('\\Acme\\Fixtures\\Types\\Marker | null', $byName['nullableObject']);
        $this->assertEquals($byName['nullableObject'], $byName['explicitNullUnion']);
        $this->assertEquals('string | null', $byName['nullableScalar']);
        $this->assertEquals('bool', $byName['plainScalar']);

        // Used to render as a lone backslash, and to warn from inside the autoloader
        $this->assertEquals(
            '\\Acme\\Fixtures\\Types\\Marker & \\Acme\\Fixtures\\Types\\Tagged',
            $byName['intersection']
        );

        // A declared "array" used to overwrite the documented element type, so
        // adding a return type declaration silently cost documentation.
        $this->assertEquals('\\Acme\\Fixtures\\Types\\Marker[]', $byName['declaredArrayOfObjects']);
        $this->assertEquals($byName['undeclaredArrayOfObjects'], $byName['declaredArrayOfObjects']);
    }

    /**
     * Splitting the file on the substring "use" could resolve neither of these;
     * both fell through to the current namespace and named a class that does not
     * exist.
     *
     * @throws \ReflectionException
     */
    public function testAliasedAndGroupedImportsResolve(): void
    {
        $reflector = new Reflector('Acme\\Fixtures\\Types\\Aliasing');
        $byName    = [];
        foreach ($reflector->getClassEntity()->getFunctions() as $function) {
            $byName[$function->getName()] = $function->getReturnType();
        }

        $this->assertEquals('\\Acme\\Fixtures\\Types\\Marker', $byName['viaAlias']);
        $this->assertEquals('\\Acme\\Fixtures\\Types\\Tagged', $byName['viaGroupImport']);
    }

    /**
     * @throws \ReflectionException
     */
    public function testNativeParameterTypesAreNotTreatedAsClassReferences(): void
    {
        $reflector = new Reflector('Acme\\Fixtures\\Types\\Shapes');
        $functions = [];
        foreach ($reflector->getClassEntity()->getFunctions() as $function) {
            $functions[$function->getName()] = $function;
        }

        $params = $functions['nullableParam']->getParams();

        $this->assertEquals('\\Acme\\Fixtures\\Types\\Marker | null', $params[0]->getType());
        // "\bool" is not a type; this used to reach the rendered table verbatim
        $this->assertEquals('bool', $params[1]->getType());
    }

    /**
     * A "@param <type> $name <description>" line must yield both the type and
     * the description — the tag word must not leak into either.
     *
     * @throws \ReflectionException
     */
    public function testDocumentedParamTypeAndDescription(): void
    {
        $reflector = new Reflector('Acme\\ClassWithDocumentedParams');
        $functions = $reflector->getClassEntity()->getFunctions();
        $params    = $functions[0]->getParams();

        $this->assertCount(3, $params);

        $this->assertEquals('$count', $params[0]->getName());
        $this->assertEquals('int', $params[0]->getType());
        $this->assertEquals('How many things', $params[0]->getDescription());

        $this->assertEquals('$label', $params[1]->getName());
        $this->assertEquals('string', $params[1]->getType());
        $this->assertEquals('Naming', $params[1]->getDescription());

        // Same declared type as $count — must not collide with it
        $this->assertEquals('$offset', $params[2]->getName());
        $this->assertEquals('int', $params[2]->getType());
        $this->assertEquals('Where to start', $params[2]->getDescription());
    }

    /**
     * A zero default is a default. "0" is falsy in PHP, so this used to vanish.
     *
     * @throws \ReflectionException
     */
    public function testFalsyDefaultsAreRetained(): void
    {
        $reflector = new Reflector('Acme\\ClassWithFalsyDefaults');
        $functions = $reflector->getClassEntity()->getFunctions();
        $params    = $functions[0]->getParams();

        $this->assertTrue($params[0]->hasDefault());
        $this->assertEquals('0', $params[0]->getDefault());
        $this->assertTrue($params[1]->hasDefault());
        $this->assertEquals('0.0', $params[1]->getDefault());
        $this->assertTrue($params[2]->hasDefault());
        $this->assertEquals('false', $params[2]->getDefault());
        $this->assertTrue($params[3]->hasDefault());
        $this->assertEquals('null', $params[3]->getDefault());
    }

    /**
     * The documented type survives a 0 default. Guards the merge branch against
     * being keyed off the formatted default again — var_export(0) is "0", which
     * is falsy, so that test would skip the merge for this parameter alone.
     *
     * @throws \ReflectionException
     */
    public function testZeroDefaultKeepsDocumentedType(): void
    {
        $reflector = new Reflector('Acme\\ClassWithFalsyDefaults');
        $functions = $reflector->getClassEntity()->getFunctions();
        $params    = $functions[1]->getParams();

        $this->assertEquals('0', $params[0]->getDefault());
        $this->assertEquals('string', $params[0]->getType());
    }

    /**
     * "@return self" refers to the declaring class, never to the method.
     *
     * @throws \ReflectionException
     */
    public function testReturnSelfResolvesToDeclaringClass(): void
    {
        $reflector = new Reflector('Acme\\ClassWithFluentInterface');
        $functions = $reflector->getClassEntity()->getFunctions();

        $this->assertEquals('fluent', $functions[0]->getName());
        $this->assertEquals('\\Acme\\ClassWithFluentInterface', $functions[0]->getReturnType());
    }

    /**
     * @throws \ReflectionException
     */
    public function testUnknownVisibilityFilterIsRejected(): void
    {
        $reflector = new Reflector('Acme\\ExampleClass');
        $reflector->setVisibilityFilter(['private']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown visibility "private"');

        $reflector->getClassEntity();
    }

    /**
     * preg_match() returns false on a malformed pattern and array_filter() reads
     * that as "exclude", so an unvalidated regex emptied every table and still
     * reported success.
     */
    public function testInvalidMethodRegexIsRejected(): void
    {
        $reflector = new Reflector('Acme\\ExampleClass');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid method regex "get"');

        $reflector->setMethodRegex('get');
    }

    /**
     * The compiler's warning is the only text that says what is wrong with the
     * pattern, so it must reach the user in the message and stay attached as
     * the cause.
     */
    public function testInvalidMethodRegexReportsTheCompilerWarning(): void
    {
        $reflector = new Reflector('Acme\\ExampleClass');

        try {
            $reflector->setMethodRegex('get');
            $this->fail('An invalid method regex was accepted');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Delimiter must not be alphanumeric', $e->getMessage());

            $previous = $e->getPrevious();
            if (!$previous instanceof ErrorException) {
                $this->fail('The compiler warning is not attached as the cause');
            }
            $this->assertSame(E_WARNING, $previous->getSeverity());
        }
    }

    /**
     * A tag whose name collides with an array-valued key used to replace that
     * array with a string; the next real @param then indexed the string and
     * fatalled.
     *
     * @throws \ReflectionException
     */
    public function testATagCollidingWithAStructuredKeyDoesNotCrash(): void
    {
        $reflector = new Reflector('Acme\\Fixtures\\Docblocks\\TagShapes');
        $functions = $reflector->getClassEntity()->getFunctions();
        $params    = $functions[0]->getParams();

        $this->assertCount(1, $params);
        $this->assertEquals('$label', $params[0]->getName());
        $this->assertEquals('What to count', $params[0]->getDescription());
    }

    /**
     * A word merely containing "@" is prose. Reading it as a tag swallowed the
     * rest of the description.
     *
     * @throws \ReflectionException
     */
    public function testAnEmailInADescriptionDoesNotTruncateIt(): void
    {
        $reflector = new Reflector('Acme\\Fixtures\\Docblocks\\TagShapes');

        $this->assertEquals(
            'Reach the maintainer. maintainer@example.com is the address to use. '
            . 'This trailing sentence must survive.',
            $reflector->getClassEntity()->getDescription()
        );
    }

    /**
     * "{@inheritDoc}" is what phpDocumentor documents and PhpStorm generates; only
     * the bare spelling used to be recognised.
     *
     * @throws \ReflectionException
     */
    public function testBracedInheritDocInheritsTheParentDescription(): void
    {
        $reflector = new Reflector('Acme\\Fixtures\\Docblocks\\BracedInheritor');
        $byName    = [];
        foreach ($reflector->getClassEntity()->getFunctions() as $function) {
            $byName[$function->getName()] = $function;
        }

        $this->assertEquals('Documented on the parent.', $byName['work']->getDescription());

        // Nothing declares this one. Describing the method itself beats aborting
        // the whole document.
        $this->assertEquals('', $byName['nothingToInheritFrom']->getDescription());
        $this->assertEquals('string', $byName['nothingToInheritFrom']->getReturnType());
    }

    /**
     * @throws \ReflectionException
     */
    public function testInheritedDocs(): void
    {
        $reflector = new Reflector('Acme\\ClassImplementingInterface');
        $functions = $reflector->getClassEntity()->getFunctions();
        $this->assertCount(4, $functions);
        $this->assertEquals('aMethod', $functions[0]->getName());
        $this->assertEquals('int', $functions[0]->getReturnType());
        $this->assertEquals('func', $functions[1]->getName());
        $this->assertEquals('\\stdClass', $functions[1]->getReturnType());
        $this->assertFalse($functions[1]->isAbstract());

        $this->assertEquals('\\FilesystemIterator', $functions[2]->getReturnType());
        $this->assertEquals('\\FilesystemIterator[]', $functions[3]->getReturnType());
    }

    /**
     * @throws \ReflectionException
     */
    public function testReferenceToImportedClass(): void
    {
        $reflector = new Reflector('Acme\\InterfaceReferringToImportedClass');
        $functions = $reflector->getClassEntity()->getFunctions();
        $this->assertEquals('\\PHPDocsMD\\Console\\CLI', $functions[1]->getReturnType());
        $this->assertEquals('\\PHPDocsMD\\Console\\CLI[]', $functions[0]->getReturnType());
    }

    public static function visibilityFiltersAndExpectedMethods(): array
    {
        return [
            'public'               => [
                ['public'],
                ['funcA', 'funcB', 'funcD', 'getFunc', 'hasFunc', 'isFunc'],
            ],
            'protected'            => [['protected'], ['funcC']],
            'public-and-protected' => [
                ['public', 'protected'],
                ['funcA', 'funcB', 'funcD', 'getFunc', 'hasFunc', 'isFunc', 'funcC'],
            ],
            'abstract'             => [['abstract'], ['isFunc']],
        ];
    }

    /**
     * @throws \ReflectionException
     */
    #[DataProvider('visibilityFiltersAndExpectedMethods')]
    public function testVisibilityBasedFiltering(array $visibilityFilter, array $expectedMethods): void
    {
        $reflector = new Reflector('Acme\\ExampleClass');
        $reflector->setVisibilityFilter($visibilityFilter);
        $functions     = $reflector->getClassEntity()->getFunctions();
        $functionNames = array_map(
            static fn (FunctionEntity $entity) => $entity->getName(),
            $functions
        );
        $this->assertEquals($expectedMethods, $functionNames);
    }

    public static function regexFiltersAndExpectedMethods(): array
    {
        return [
            'has-only'              => ['/^has/', ['hasFunc']],
            'does-not-start-with-h' => [
                '/^[^h]/',
                ['funcA', 'funcB', 'funcD', 'getFunc', 'isFunc', 'funcC'],
            ],
            'func-letter-only'      => ['/^func[A-Z]/', ['funcA', 'funcB', 'funcD', 'funcC']],
        ];
    }

    /**
     * @throws \ReflectionException
     */
    #[DataProvider('regexFiltersAndExpectedMethods')]
    public function testMethodRegexFiltering($regexFilter, $expectedMethods): void
    {
        $reflector = new Reflector('Acme\\ExampleClass');
        $reflector->setMethodRegex($regexFilter);
        $functions     = $reflector->getClassEntity()->getFunctions();
        $functionNames = array_map(
            static fn (FunctionEntity $entity) => $entity->getName(),
            $functions
        );
        $this->assertEquals($expectedMethods, $functionNames);
    }

    /**
     * @throws \ReflectionException
     */
    private function functionsOf(string $class): array
    {
        $byName = [];
        foreach ((new Reflector($class))->getClassEntity()->getFunctions() as $function) {
            $byName[$function->getName()] = $function;
        }

        return $byName;
    }

    /**
     * The "*" decoration was stripped with an unanchored pattern, so an asterisk
     * in the prose went too, along with the space on each side.
     *
     * @throws \ReflectionException
     */
    public function testAnAsteriskInADescriptionSurvives(): void
    {
        $reflector = new Reflector('Acme\\Fixtures\\Docblocks\\Prose');

        $this->assertEquals(
            'Area is width * height in pixels.',
            $reflector->getClassEntity()->getDescription()
        );
    }

    /**
     * A wrapped @param line continues that parameter's description. It used to
     * fall through and be appended to the method description instead.
     *
     * @throws \ReflectionException
     */
    public function testAWrappedParamLineStaysWithItsParameter(): void
    {
        $wrapped = $this->functionsOf('Acme\\Fixtures\\Docblocks\\Prose')['wrapped'];
        $params  = [];
        foreach ($wrapped->getParams() as $param) {
            $params[$param->getName()] = $param;
        }

        $this->assertEquals(
            'Multiplies 2 * 3 to get six. {@link https://example.com Example}',
            $wrapped->getDescription()
        );
        $this->assertEquals(
            'The name of the thing, which needs quite a long explanation to describe.',
            $params['$name']->getDescription()
        );
        $this->assertEquals('A short one.', $params['$n']->getDescription());
    }

    /**
     * "@return <type> <description>" is the documented form; storing the whole
     * line rendered the description as though it were part of the type.
     *
     * @throws \ReflectionException
     */
    public function testOnlyTheFirstWordOfReturnIsTheType(): void
    {
        $functions = $this->functionsOf('Acme\\Fixtures\\Docblocks\\Prose');

        $this->assertEquals('string', $functions['returnsWithDescription']->getReturnType());
    }

    /**
     * A bare "@deprecated" is the most common form, and testing the message read
     * it as not deprecated at all.
     *
     * @throws \ReflectionException
     */
    public function testDeprecatedWithoutAMessageIsStillDeprecated(): void
    {
        $functions = $this->functionsOf('Acme\\Fixtures\\Docblocks\\Prose');

        $this->assertTrue($functions['deprecatedWithoutMessage']->isDeprecated());
        $this->assertEquals('', $functions['deprecatedWithoutMessage']->getDeprecationMessage());
    }

    /**
     * The declared type is the one the engine enforces. Comparing the docblock
     * against FunctionEntity's 'void' initialiser meant "@return void" — and only
     * that — discarded the declaration.
     *
     * @throws \ReflectionException
     */
    public function testADocblockSayingVoidDoesNotSuppressTheDeclaredType(): void
    {
        $functions = $this->functionsOf('Acme\\Fixtures\\Types\\Modifiers');

        $this->assertEquals('string', $functions['contradictedReturnType']->getReturnType());
    }

    /**
     * "..." and "&" are separate reflection flags, so the rendered signature was
     * not the signature: "f(...$a)" documented as "f($a)".
     *
     * @throws \ReflectionException
     */
    public function testVariadicAndByReferenceMarkersAreRendered(): void
    {
        $functions = $this->functionsOf('Acme\\Fixtures\\Types\\Modifiers');

        $variadic = $functions['variadic']->getParams();
        $this->assertEquals('$first', $variadic[0]->getName());
        $this->assertEquals('...$rest', $variadic[1]->getName());
        // The docblock entry is keyed by the bare name now, so its text reaches
        // the parameter it describes.
        $this->assertEquals('the variadic one', $variadic[1]->getDescription());

        $byRef = $functions['byRef']->getParams();
        $this->assertEquals('&$out', $byRef[0]->getName());
        $this->assertEquals('filled by the callee', $byRef[0]->getDescription());
    }

    /**
     * A member the docblock and the declaration both state is listed once. The
     * two were once compared as whole strings, spaced differently, which made
     * them read as disjoint and listed every member twice.
     *
     * @throws \ReflectionException
     */
    public function testADocumentedUnionIsMergedRatherThanConcatenated(): void
    {
        $functions = $this->functionsOf('Acme\\Fixtures\\Types\\Modifiers');
        $params    = $functions['documentedUnions']->getParams();

        foreach ($params as $param) {
            $members = array_map('trim', explode('|', $param->getType()));
            $this->assertEquals(
                $members,
                array_values(array_unique($members)),
                'union members must not repeat, got: ' . $param->getType()
            );
        }
    }

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
     * A wrapped @return line is prose this tool does not render; it must not be
     * appended to the method description.
     *
     * @throws \ReflectionException
     */
    public function testAWrappedReturnLineStaysOutOfTheDescription(): void
    {
        $function = $this->functionOf('Acme\\Fixtures\\Docblocks\\Continuations', 'wrappedReturn');

        $this->assertSame('Describes the return value at length.', $function->getDescription());
        $this->assertSame('string', $function->getReturnType());
    }

    /**
     * "@params" is a tag this tool does not know, like @author or @since: it
     * documents no parameter and is not prose either.
     *
     * @throws \ReflectionException
     */
    public function testAMistypedTagAfterAParamDocumentsNothing(): void
    {
        $function = $this->functionOf('Acme\\Fixtures\\Docblocks\\Continuations', 'strayAfterParam');

        $this->assertSame('Lists a parameter, then mistypes the next tag.', $function->getDescription());
        $this->assertCount(1, $function->getParams());
        $this->assertSame('the name', $function->getParams()[0]->getDescription());
    }

    /**
     * "@param string" names no parameter, so it documents none, and its wrapped
     * line has no parameter to belong to.
     *
     * @throws \ReflectionException
     */
    public function testAParamTagWithoutANameDocumentsNothing(): void
    {
        $function = $this->functionOf('Acme\\Fixtures\\Docblocks\\Continuations', 'unnamedParam');
        $param = $function->getParams()[0];

        $this->assertSame('Documents a parameter type but forgets its name.', $function->getDescription());
        $this->assertSame('string', $param->getType());
        $this->assertSame('', $param->getDescription());
    }

    /**
     * A tag the parser cannot read is recorded as a problem rather than lost
     * without a trace, and a @param that still names its parameter keeps its
     * description.
     *
     * @throws \ReflectionException
     */
    public function testAnUnreadableTagIsReportedAndKeepsWhatItCan(): void
    {
        $function = $this->functionOf('Acme\\Fixtures\\Docblocks\\Continuations', 'malformedTags');

        $this->assertSame('the items to count', $function->getParams()[0]->getDescription());
        $problems = $function->getProblems();
        $this->assertCount(2, $problems);
        $this->assertStringStartsWith('cannot read "@param array<int $items the items to count": ', $problems[0]);
        $this->assertStringStartsWith('cannot read "@return int<0, the count": ', $problems[1]);
    }

    /**
     * A quoted phrase may wrap like any other prose; the next line's " * " is
     * decoration, not part of the quote.
     *
     * @throws \ReflectionException
     */
    public function testAQuoteThatWrapsLosesTheDecoration(): void
    {
        $function = $this->functionOf('Acme\\Fixtures\\Docblocks\\Continuations', 'quoteWrapsALine');

        $this->assertSame(
            'Quotes a phrase that wraps: "the first half and the second half" read as one.',
            $function->getDescription()
        );
        $this->assertSame('a "name that also wraps" inside its quotes', $function->getParams()[0]->getDescription());
    }

    /**
     * An unpaired quote — inches, here — must not pair with the next quote in
     * the docblock and take every tag in between with it.
     *
     * @throws \ReflectionException
     */
    public function testAStrayQuoteDoesNotSwallowTheTagsAfterIt(): void
    {
        $function = $this->functionOf('Acme\\Fixtures\\Docblocks\\Continuations', 'strayQuote');

        $this->assertSame('Fits a 12" pipe.', $function->getDescription());
        $this->assertSame('the "inner" diameter', $function->getParams()[0]->getDescription());
    }

    /**
     * {@inheritDoc} reaches past a parent that does not declare the method to
     * the ancestor that does.
     *
     * @throws \ReflectionException
     */
    public function testInheritDocReachesAGrandparent(): void
    {
        $function = $this->functionOf('Acme\\Fixtures\\Docblocks\\InheritGrandchild', 'work');

        $this->assertSame('Documented on the parent.', $function->getDescription());
        $this->assertSame('a number', $function->getParams()[0]->getDescription());
        $this->assertFalse($function->isAbstract());
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

    /**
     * @throws \ReflectionException
     */
    private function functionOf(string $className, string $method): FunctionEntity
    {
        foreach ((new Reflector($className))->getClassEntity()->getFunctions() as $function) {
            if ($function->getName() === $method) {
                return $function;
            }
        }

        $this->fail(sprintf('%s has no documented method %s()', $className, $method));
    }

    /**
     * @throws \ReflectionException
     */
    protected function setUp(): void
    {
        // require_once __DIR__ . '/Acme/ExampleClass.php';
        $this->reflector = new Reflector('Acme\\ExampleClass');
        $this->class     = $this->reflector->getClassEntity();
    }
}
