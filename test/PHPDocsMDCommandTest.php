<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use InvalidArgumentException;
use PHPDocsMD\Console\PHPDocsMDCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class PHPDocsMDCommandTest extends TestCase
{
    private CommandTester $tester;

    protected function setUp(): void
    {
        $application = new Application();
        $application->addCommands([new PHPDocsMDCommand()]);

        $this->tester = new CommandTester($application->find('generate'));
        SpyTableGenerator::$seenIncludeSee = [];
    }

    /**
     * --ignore must apply at every depth, not only directly below the search root.
     */
    public function testIgnoreAppliesToNestedDirectories(): void
    {
        $this->tester->execute([
            'class'    => __DIR__ . '/Acme/Fixtures/Tree',
            '--ignore' => 'skipme',
        ]);

        $output = $this->tester->getDisplay();

        $this->assertStringContainsString('Acme\\Fixtures\\Tree\\Keep\\Kept', $output);
        $this->assertStringNotContainsString('TopLevelSkipped', $output);
        $this->assertStringNotContainsString('NestedSkipped', $output);
    }

    /**
     * A class name that is a prefix of another documented class must not have
     * the shorter one's link substituted into it.
     */
    public function testCrossReferencesDoNotCorruptPrefixSharingNames(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Links']);

        $output = $this->tester->getDisplay();

        $this->assertStringContainsString(
            '[\\Acme\\Fixtures\\Links\\BarBaz](#class-acme-fixtures-links-barbaz)',
            $output
        );
        $this->assertStringNotContainsString('](#interface-acme-fixtures-links-bar)Baz', $output);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nestedClassReferences(): array
    {
        $bar = '[\\Acme\\Fixtures\\Links\\Bar](#interface-acme-fixtures-links-bar)';
        $barBaz = '[\\Acme\\Fixtures\\Links\\BarBaz](#class-acme-fixtures-links-barbaz)';
        $manual = 'https://php.net/manual/en/';

        $arrayObject = "[\\ArrayObject]({$manual}class.arrayobject.php)";
        $countable = "[\\Countable]({$manual}class.countable.php)";
        $randomizer = "[\\Random\\Randomizer]({$manual}class.random-randomizer.php)";
        $boundary = "[\\Random\\IntervalBoundary]({$manual}enum.random-intervalboundary.php)";

        return [
            'a generic parameter'                => ["<em>list&lt;{$bar}&gt;</em>"],
            'a map value in a union'             => ["<em>array&lt;string, {$barBaz}&gt; \\| null</em>"],
            'shape values'                       => ["<em>array{bar: {$bar}, baz?: {$barBaz}}</em>"],
            'a callable signature'               => ["<em>callable({$bar}): {$barBaz}</em>"],
            'a PHP class with parameters'        => ["<em>{$arrayObject}&lt;int, {$bar}&gt;</em>"],
            'an element type'                    => ["<em>{$bar}[]</em>"],
            'a class constant'                   => ["<em>{$bar}::FIRST</em>"],
            'a PHP interface in an intersection' => ["<em>{$countable} &amp; {$bar}</em>"],
            'a namespaced PHP class'             => ["<em>{$randomizer}</em>"],
            'a PHP enum'                         => ["<em>{$boundary}</em>"],
            // PHP class names are case-insensitive; the text stays as written
            'a name in another case'             => [
                '<em>[\\acme\\fixtures\\links\\bar](#interface-acme-fixtures-links-bar)</em>',
            ],
        ];
    }

    /**
     * A class name is a link wherever it sits in a type, not only as a whole
     * type or a union member. PHP's own classes, interfaces and enums link to
     * their manual pages, whose names replace each namespace separator with a
     * hyphen and file enums apart from classes.
     */
    #[DataProvider('nestedClassReferences')]
    public function testClassNamesAreLinkedWhereverTheySitInAType(string $expected): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Links']);

        $this->assertStringContainsString($expected, $this->tester->getDisplay());
    }

    /**
     * Prose is the author's markdown, and a link inside a code span is shown as
     * the link syntax itself. A string literal holds a string, not a class: its
     * backslashes are escaped, so it never reads as a name.
     */
    public function testClassNamesInProseAndStringLiteralsAreNotLinked(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Links']);

        $this->assertStringContainsString(
            "<strong>nameInText()</strong> : <em>'\\\\Acme\\\\Fixtures\\\\Links\\\\Bar' \\| '\\\\Countable'</em>"
            . '<br /><em>Returns `\\Acme\\Fixtures\\Links\\Bar` objects.</em>',
            $this->tester->getDisplay()
        );
    }

    /**
     * @return array<string, array{array<string, bool>, string}>
     */
    public static function inheritanceLines(): array
    {
        $ns = '\\Acme\\Fixtures\\Hierarchy\\';

        return [
            'a parent left out by @ignore'      => [[], "*This class extends {$ns}IgnoredBase*"],
            'a documented parent'               => [
                [],
                "*This class extends [{$ns}InternalBase](#class-acme-fixtures-hierarchy-internalbase-abstract)*",
            ],
            'a parent left out by --no-internal' => [
                ['--no-internal' => true],
                "*This class extends {$ns}InternalBase*",
            ],
            'two documented interfaces'         => [
                [],
                "*This class implements [{$ns}First](#interface-acme-fixtures-hierarchy-first), "
                . "[{$ns}Second](#interface-acme-fixtures-hierarchy-second)*",
            ],
            'a PHP parent'                      => [
                [],
                '*This class extends [\\ArrayObject](https://php.net/manual/en/class.arrayobject.php)*',
            ],
        ];
    }

    /**
     * A parent or interface links to its section only when the document has
     * one. One left out by @ignore or --no-internal was linked to an anchor
     * nothing wrote. PHP's own classes link to the manual, as in type cells.
     *
     * @param array<string, bool> $options
     */
    #[DataProvider('inheritanceLines')]
    public function testInheritanceLinesLinkOnlyToWrittenSections(array $options, string $expected): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Hierarchy'] + $options);

        $this->assertStringContainsString($expected, $this->tester->getDisplay());
    }

    /**
     * The markup convention TableGenerator documents holds for any generator:
     * class names in an <em> are linked, and an <em> after "<br />" is prose
     * and is left as the author wrote it.
     */
    public function testAThirdPartyGeneratorGetsTheDocumentedLinking(): void
    {
        $this->tester->execute([
            'class'            => __DIR__ . '/Acme/Fixtures/Links',
            '--tableGenerator' => MarkupTableGenerator::class,
        ]);
        $output = $this->tester->getDisplay();

        $this->assertStringContainsString(
            '- listOfBar : <em>list<[\\Acme\\Fixtures\\Links\\Bar](#interface-acme-fixtures-links-bar)></em>',
            $output
        );
        $this->assertStringContainsString('<br /><em>Returns `\\Acme\\Fixtures\\Links\\Bar` objects.</em>', $output);
    }

    /**
     * A class that is not loaded is neither documented here nor PHP's own, and
     * it is not loaded to find out: loading may fail, or run the class's file.
     */
    public function testAClassThatIsNotLoadedIsNotLinked(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Links']);

        $this->assertStringContainsString(
            '<strong>notLoaded()</strong> : <em>\\Acme\\Fixtures\\Broken\\NotInstalled\\MissingBase</em>',
            $this->tester->getDisplay()
        );
    }

    /**
     * A single class is written without section anchors, so nothing may link
     * to one — a fluent method's own class was linked to a section that was
     * never written. Nor is a class of the project PHP's to document.
     */
    public function testASingleClassLinksToNoSection(): void
    {
        $this->tester->execute([
            'class'       => 'Acme\\ClassWithFluentInterface',
            '--bootstrap' => __DIR__ . '/Acme/ExampleClass.php',
        ]);

        $this->assertStringContainsString(
            '<strong>fluent()</strong> : <em>\\Acme\\ClassWithFluentInterface</em>',
            $this->tester->getDisplay()
        );
    }

    /**
     * A union return type must stay inside one table cell.
     */
    public function testPipesAreEscapedInsideTableCells(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Links']);

        foreach (explode(PHP_EOL, $this->tester->getDisplay()) as $line) {
            if (!str_starts_with($line, '| public') && !str_starts_with($line, '| protected')) {
                continue;
            }
            // "| visibility | signature |" -> exactly 3 unescaped pipes
            $withoutEscaped = str_replace('\|', '', $line);
            $this->assertEquals(
                3,
                substr_count($withoutEscaped, '|'),
                'Row broke out of its two columns: ' . $line
            );
        }
    }

    public function testSeeFlagReachesTheTableGenerator(): void
    {
        $this->tester->execute([
            'class'            => __DIR__ . '/Acme/Fixtures/Links',
            '--tableGenerator' => SpyTableGenerator::class,
            '--see'            => true,
        ]);

        $this->assertNotEmpty(SpyTableGenerator::$seenIncludeSee);
        $this->assertEquals(
            [true],
            array_unique(SpyTableGenerator::$seenIncludeSee),
            '--see was not forwarded to the table generator'
        );
    }

    public function testTableGeneratorMustImplementTheInterface(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('should implement');

        $this->tester->execute([
            'class'            => __DIR__ . '/Acme/Fixtures/Links',
            '--tableGenerator' => stdClass::class,
        ]);
    }

    public function testUnknownInputIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('neither a class nor a source directory');

        $this->tester->execute(['class' => 'Definitely\\Not\\A\\Class']);
    }

    /**
     * --ignore takes directory names. It used to be a suffix test, so "me"
     * silently removed every "skipme".
     */
    public function testIgnoreMatchesWholeDirectoryNamesOnly(): void
    {
        $this->tester->execute([
            'class'    => __DIR__ . '/Acme/Fixtures/Tree',
            '--ignore' => 'me',
        ]);

        $output = $this->tester->getDisplay();

        $this->assertStringContainsString('TopLevelSkipped', $output);
        $this->assertStringContainsString('NestedSkipped', $output);
    }

    /**
     * A directory scan must find every kind of type declaration, not only the
     * two whose keyword the scanner happened to look for.
     */
    public function testEveryDeclarationKindIsDiscovered(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Kinds']);

        $output = $this->tester->getDisplay();

        $this->assertStringContainsString('Acme\\Fixtures\\Kinds\\PlainClass', $output);
        $this->assertStringContainsString('Acme\\Fixtures\\Kinds\\PlainTrait', $output);
        $this->assertStringContainsString('Acme\\Fixtures\\Kinds\\PlainEnum', $output);
    }

    /**
     * A trait cannot be instantiated and an enum has closed instances; the label
     * has to say which one it is. Everything that was not an interface used to be
     * called a class, in the heading, the anchor and the table of contents alike.
     */
    public function testEachDeclarationKindIsLabelledAsItself(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Kinds']);

        $output = $this->tester->getDisplay();

        $this->assertStringContainsString('### Class: \\Acme\\Fixtures\\Kinds\\PlainClass', $output);
        $this->assertStringContainsString('### Trait: \\Acme\\Fixtures\\Kinds\\PlainTrait', $output);
        $this->assertStringContainsString('### Enum: \\Acme\\Fixtures\\Kinds\\PlainEnum', $output);
        $this->assertStringContainsString('#trait-acme-fixtures-kinds-plaintrait', $output);
        $this->assertStringContainsString('#enum-acme-fixtures-kinds-plainenum', $output);
    }

    /**
     * Whether a class is found must not depend on the line endings of the file
     * it is declared in.
     */
    public function testClassInACrlfFileIsDiscovered(): void
    {
        $fixture = __DIR__ . '/Acme/Fixtures/Kinds/CrLfClass.php';

        // Guard the fixture, not just the behaviour: the file was normalised to
        // LF at some point, which left this test passing while covering nothing.
        $this->assertStringContainsString(
            "\r\n",
            (string)file_get_contents($fixture),
            'the CRLF fixture has been normalised to LF — this test would pass vacuously'
        );

        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Kinds']);

        $this->assertStringContainsString('Acme\\Fixtures\\Kinds\\CrLfClass', $this->tester->getDisplay());
    }

    /**
     * Reflection resolves a class_alias to the original, so a rename shim and the
     * class it points at yielded two identical sections under one anchor id.
     */
    public function testAClassReachedThroughAnAliasIsDocumentedOnce(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Aliased']);

        $output = $this->tester->getDisplay();

        $this->assertSame(
            1,
            substr_count($output, '<a id="class-acme-fixtures-aliased-original">'),
            'an HTML id must be unique'
        );
        $this->assertSame(
            1,
            substr_count($output, '- [\\Acme\\Fixtures\\Aliased\\Original]'),
            'the class must appear once in the table of contents'
        );
        $this->assertStringNotContainsString('Renamed', $output);
    }

    /**
     * Only the first member of a union sits directly after "<em>"; the rest are
     * preceded by the escaped pipe, which the substitution did not accept.
     */
    public function testEveryMemberOfAUnionReturnTypeIsLinked(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Links']);

        $output = $this->tester->getDisplay();

        $this->assertStringContainsString(
            '[\\Acme\\Fixtures\\Links\\Bar](#interface-acme-fixtures-links-bar)',
            $output
        );
        $this->assertStringContainsString(
            '[\\Acme\\Fixtures\\Links\\BarBaz](#class-acme-fixtures-links-barbaz)',
            $output
        );
        $this->assertMatchesRegularExpression(
            '/makeEither.*\[\\\\Acme\\\\Fixtures\\\\Links\\\\Bar\].*\[\\\\Acme\\\\Fixtures\\\\Links\\\\BarBaz\]/',
            $output,
            'both members of the union return type must be links'
        );
    }

    /**
     * A misspelled generator used to fall back to the default: wrong format,
     * exit 0, no warning. --visibility rejects its unknown values; so does this.
     */
    public function testAnUnknownTableGeneratorIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown table generator "NoSuchGeneratorClass"');

        $this->tester->execute([
            'class'            => __DIR__ . '/Acme/Fixtures/Kinds',
            '--tableGenerator' => 'NoSuchGeneratorClass',
        ]);
    }

    /**
     * VALUE_OPTIONAL yields null, not the declared default, when the flag carries
     * no value — which reached a string parameter and raised a TypeError.
     */
    public function testTableGeneratorWithoutAValueFallsBackToTheDefault(): void
    {
        $exitCode = $this->tester->execute([
            'class'            => __DIR__ . '/Acme/Fixtures/Kinds',
            '--tableGenerator' => null,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('| Visibility | Function |', $this->tester->getDisplay());
    }

    /**
     * The diagnostic went to STDERR directly, so --quiet could not silence it and
     * CommandTester could not see it — it printed into the test runner's output.
     */
    public function testTheSkippedClassWarningGoesThroughTheConsoleOutput(): void
    {
        $this->tester->execute(
            ['class' => __DIR__ . '/Acme/Fixtures/Broken'],
            ['capture_stderr_separately' => true]
        );

        $this->assertStringContainsString(
            'phpdoc-md: skipping',
            $this->tester->getErrorOutput()
        );
        $this->assertStringContainsString('UnloadableChild', $this->tester->getErrorOutput());
        $this->assertStringNotContainsString('phpdoc-md: skipping', $this->tester->getDisplay());
    }

    /**
     * The output is a document, not console chrome. Symfony's formatter used to
     * eat any <info>/<comment>/<error> that appeared in a docblock.
     */
    public function testConsoleStyleTagsInDocblocksSurvive(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Kinds']);

        $this->assertStringContainsString('<info>markup</info>', $this->tester->getDisplay());
    }

    /**
     * A class whose parent is not installed cannot be reflected. Skipping it must
     * cost that one class — it used to abort the run and emit nothing at all.
     */
    public function testAnUnloadableClassDoesNotAbortTheRun(): void
    {
        $exitCode = $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Broken']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Acme\\Fixtures\\Broken\\Sibling', $this->tester->getDisplay());
    }

    /**
     * Two class names differing only in where the namespace boundary falls must
     * not share an anchor, or every link to the second resolves to the first.
     */
    public function testAnchorsDistinguishNamespaceBoundaries(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Anchors']);

        $anchors = [];
        preg_match_all('/<a id="([^"]+)"/', $this->tester->getDisplay(), $anchors);

        $this->assertCount(2, $anchors[1]);
        $this->assertEquals($anchors[1], array_unique($anchors[1]), 'two classes share one anchor');
    }

    /**
     * formatExampleComment() is part of the TableGenerator contract, so the
     * selected generator must render class-level examples too — the built-in one
     * used to be hardcoded here.
     */
    public function testClassLevelExamplesGoThroughTheSelectedGenerator(): void
    {
        $this->tester->execute([
            'class'            => 'Acme\\Fixtures\\Docblocks\\WithClassExample',
            '--tableGenerator' => SpyTableGenerator::class,
        ]);

        $output = $this->tester->getDisplay();

        // SpyTableGenerator returns the example verbatim; a fence would mean
        // MDTableGenerator rendered it instead.
        $this->assertStringContainsString('###### Example', $output);
        $this->assertStringNotContainsString('```', $output);
    }

    /**
     * A blank line inside an @example is part of the sample. The decoration
     * stripper used to consume the newline of the bare " *" line that carries it,
     * splicing the statements on either side onto one line.
     */
    public function testBlankLinesInsideAnExampleSurvive(): void
    {
        $this->tester->execute(['class' => 'Acme\\Fixtures\\Docblocks\\WithClassExample']);
        $output = $this->tester->getDisplay();

        // The blank line between the two statements has to survive as a blank line
        $this->assertMatchesRegularExpression('/\$first = 1;\R\R\s*\$second = 2;/', $output);
        $this->assertStringNotContainsString('$first = 1;$second', $output);
    }

    /**
     * The generator's appendExamples toggle was implemented and unit-tested but
     * no option reached it, so it was permanently on.
     */
    public function testExampleBlocksCanBeSuppressed(): void
    {
        $this->tester->execute(['class' => 'Acme\\Fixtures\\Docblocks\\WithClassExample']);
        $this->assertStringContainsString('###### Examples of', $this->tester->getDisplay());

        $this->tester->execute([
            'class'         => 'Acme\\Fixtures\\Docblocks\\WithClassExample',
            '--no-examples' => true,
        ]);
        $this->assertStringNotContainsString('###### Examples of', $this->tester->getDisplay());
    }

    /**
     * Same sources must always produce the same document.
     */
    public function testOutputOrderIsDeterministic(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Tree']);
        $first = $this->tester->getDisplay();

        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Tree']);
        $second = $this->tester->getDisplay();

        $this->assertEquals($first, $second);
    }

    /**
     * A parameter typed with a class PHP itself ships links to its php.net page.
     */
    public function testNativeClassParameterLinksToTheManual(): void
    {
        $this->tester->execute(['class' => 'Acme\\Fixtures\\Natives\\TakesNativeParam']);

        $this->assertStringContainsString(
            '[\\ArrayObject](https://php.net/manual/en/class.arrayobject.php)',
            $this->tester->getDisplay()
        );
    }

    /**
     * A parent class documented in the same run is linked to its section.
     */
    public function testParentInTheSameDocumentIsLinked(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Docblocks']);

        $this->assertStringContainsString(
            '*This class extends [\\Acme\\Fixtures\\Docblocks\\InheritBase]'
            . '(#class-acme-fixtures-docblocks-inheritbase-abstract)*',
            $this->tester->getDisplay()
        );
    }

    /**
     * A parent outside the document has no section to link to, so it is named
     * without a link rather than with an empty "(#)" target.
     */
    public function testParentOutsideTheDocumentIsNotLinked(): void
    {
        $this->tester->execute(['class' => 'Acme\\Fixtures\\Docblocks\\BracedInheritor']);

        $this->assertStringContainsString(
            '*This class extends \\Acme\\Fixtures\\Docblocks\\InheritBase*',
            $this->tester->getDisplay()
        );
    }

    /**
     * Every @example is kept: a second one used to be glued onto the end of
     * the first without so much as a line break.
     */
    public function testEveryExampleIsShown(): void
    {
        $this->tester->execute(['class' => 'Acme\\Fixtures\\Docblocks\\Continuations']);

        $this->assertStringContainsString(
            '###### Examples of Continuations::twoExamples()' . "\n```\n\$first = 1;\n\n\$second = 2;\n```",
            $this->tester->getDisplay()
        );
    }

    /**
     * An unreadable tag is named on stderr with the method it belongs to, so
     * documentation the tool could not carry over is never lost in silence.
     */
    public function testAnUnreadableTagIsReported(): void
    {
        $this->tester->execute(
            ['class' => 'Acme\\Fixtures\\Docblocks\\Continuations'],
            ['capture_stderr_separately' => true]
        );

        $this->assertStringContainsString(
            'phpdoc-md: \\Acme\\Fixtures\\Docblocks\\Continuations::malformedTags(): '
            . 'cannot read "@param array<int $items the items to count": ',
            $this->tester->getErrorOutput()
        );
    }

    public function testDeprecatedClassTitleIsStruckThrough(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Deprecation']);
        $output = $this->tester->getDisplay();

        $this->assertStringContainsString(
            '### <del>Class: \\Acme\\Fixtures\\Deprecation\\Retired</del>' . PHP_EOL . PHP_EOL
            . '> **DEPRECATED** use Replacement instead' . PHP_EOL,
            $output
        );
        // A bare tag has no message to follow the label, so no trailing space.
        $this->assertStringContainsString(
            '### <del>Class: \\Acme\\Fixtures\\Deprecation\\RetiredWithoutMessage</del>' . PHP_EOL . PHP_EOL
            . '> **DEPRECATED**' . PHP_EOL,
            $output
        );
    }

    /**
     * Deprecation adds a notice; it does not take the description's place.
     */
    public function testDeprecatedClassKeepsItsDescription(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Deprecation']);

        $this->assertStringContainsString(
            '> **DEPRECATED** use Replacement instead' . PHP_EOL . PHP_EOL
            . '> Replaced by something better.' . PHP_EOL,
            $this->tester->getDisplay()
        );
    }

    public function testNativeClassReturnTypesLinkToTheManual(): void
    {
        $this->tester->execute(['class' => 'Acme\\Fixtures\\Natives\\ReturnsNative']);
        $output = $this->tester->getDisplay();

        // The link covers the class name and nothing else: the "[]" belongs to
        // the type, as it does after a documented class.
        $this->assertStringContainsString(
            '<em>[\\ArrayIterator](https://php.net/manual/en/class.arrayiterator.php)</em>',
            $output
        );
        $this->assertStringContainsString(
            '<em>[\\SplStack](https://php.net/manual/en/class.splstack.php)[]</em>',
            $output
        );
    }

    public function testACommaSeparatedListDocumentsEachClass(): void
    {
        $this->tester->execute(['class' => 'Acme\\Fixtures\\Links\\Bar,Acme\\Fixtures\\Links\\BarBaz']);
        $output = $this->tester->getDisplay();

        $this->assertStringContainsString(
            '- [\\Acme\\Fixtures\\Links\\Bar (interface)](#interface-acme-fixtures-links-bar)',
            $output
        );
        $this->assertStringContainsString(
            '- [\\Acme\\Fixtures\\Links\\BarBaz](#class-acme-fixtures-links-barbaz)',
            $output
        );
    }

    /**
     * A listed name that is not a class is reported and kept in the table of
     * contents, in its place, as text: there is no section to link to. The
     * classes around it are documented as usual.
     */
    public function testACommaSeparatedListKeepsAMissingClassAsText(): void
    {
        $this->tester->execute(
            ['class' => 'Acme\\Fixtures\\Links\\Bar,Acme\\Fixtures\\Links\\Missing,Acme\\Fixtures\\Links\\BarBaz'],
            ['capture_stderr_separately' => true]
        );
        $output = $this->tester->getDisplay();

        $this->assertStringContainsString(
            '- [\\Acme\\Fixtures\\Links\\Bar (interface)](#interface-acme-fixtures-links-bar)' . PHP_EOL
            . '- \\Acme\\Fixtures\\Links\\Missing' . PHP_EOL
            . '- [\\Acme\\Fixtures\\Links\\BarBaz](#class-acme-fixtures-links-barbaz)' . PHP_EOL,
            $output
        );
        $this->assertStringContainsString('### Class: \\Acme\\Fixtures\\Links\\BarBaz', $output);
        $this->assertStringContainsString(
            'phpdoc-md: skipping \\Acme\\Fixtures\\Links\\Missing',
            $this->tester->getErrorOutput()
        );
    }

    /**
     * Spaces after the commas, and the empty entries a doubled or a trailing
     * comma leaves, are list punctuation, not class names.
     */
    public function testACommaSeparatedListToleratesSpacesAndEmptyEntries(): void
    {
        $this->tester->execute(
            ['class' => 'Acme\\Fixtures\\Links\\Bar, , Acme\\Fixtures\\Links\\BarBaz,'],
            ['capture_stderr_separately' => true]
        );

        $this->assertStringContainsString(
            '- [\\Acme\\Fixtures\\Links\\Bar (interface)](#interface-acme-fixtures-links-bar)' . PHP_EOL
            . '- [\\Acme\\Fixtures\\Links\\BarBaz](#class-acme-fixtures-links-barbaz)' . PHP_EOL,
            $this->tester->getDisplay()
        );
        $this->assertSame('', $this->tester->getErrorOutput());
    }

    /**
     * Listed classes link to each other the way a scanned directory's do. The
     * names were kept as typed, without the leading backslash the parent and
     * interface names carry, so none of them ever matched.
     */
    public function testACommaSeparatedListLinksToTheInterfacesItDocuments(): void
    {
        $this->tester->execute(['class' => 'Acme\\Fixtures\\Links\\BarBaz,Acme\\Fixtures\\Links\\Bar']);

        $this->assertStringContainsString(
            '*This class implements [\\Acme\\Fixtures\\Links\\Bar](#interface-acme-fixtures-links-bar)*',
            $this->tester->getDisplay()
        );
    }

    /**
     * Listing names is not documenting them: with no class found there is no
     * document to write.
     */
    public function testACommaSeparatedListOfMissingClassesDocumentsNothing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No classes found');

        $this->tester->execute(
            ['class' => 'Acme\\Fixtures\\Links\\Missing,Acme\\Fixtures\\Links\\Absent'],
            ['capture_stderr_separately' => true]
        );
    }
}
