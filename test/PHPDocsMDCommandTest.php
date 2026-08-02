<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use InvalidArgumentException;
use PHPDocsMD\Console\PHPDocsMDCommand;
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
        $command = new PHPDocsMDCommand();

        // Application::add() was removed in Symfony 8; addCommand() replaced it in 7.4.
        if (method_exists($application, 'addCommand')) {
            $application->addCommand($command);
        } else {
            $application->add($command);
        }

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
        $this->assertStringNotContainsString('](#interface-acme-fixtures-links-bar) Baz', $output);
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
}
