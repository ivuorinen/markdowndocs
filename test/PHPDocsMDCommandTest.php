<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use InvalidArgumentException;
use stdClass;

class PHPDocsMDCommandTest extends CommandTestCase
{
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
     * --visibility narrows the documented methods to the visibilities it lists.
     */
    public function testVisibilityOptionLimitsTheDocumentedMethods(): void
    {
        $this->tester->execute([
            'class'        => 'Acme\\ExampleClass',
            '--visibility' => 'public',
        ]);
        $output = $this->tester->getDisplay();

        $this->assertStringContainsString('<strong>funcA(', $output);
        $this->assertStringNotContainsString('<strong>funcC(', $output);
    }

    /**
     * The list is comma-separated, and whitespace around an entry is not part
     * of the visibility name. Left in, it fails the unknown-visibility check.
     */
    public function testVisibilityListToleratesSurroundingWhitespace(): void
    {
        $this->tester->execute([
            'class'        => 'Acme\\ExampleClass',
            '--visibility' => ' protected , public ',
        ]);
        $output = $this->tester->getDisplay();

        $this->assertStringContainsString('<strong>funcA(', $output);
        $this->assertStringContainsString('<strong>funcC(', $output);
    }

    /**
     * A relative --bootstrap path is resolved against the working directory,
     * not the include_path or the directory of the executable.
     */
    public function testRelativeBootstrapIsResolvedAgainstTheWorkingDirectory(): void
    {
        $cwd = getcwd();
        $this->assertIsString($cwd);
        chdir(__DIR__);
        try {
            $this->tester->execute([
                'class'       => 'Acme\\Fixtures\\Bootstrap\\OnlyViaBootstrap',
                '--bootstrap' => 'Acme/Fixtures/Bootstrap/bootstrap.php',
            ]);
        } finally {
            chdir($cwd);
        }

        $this->assertStringContainsString(
            '### Class: \\Acme\\Fixtures\\Bootstrap\\OnlyViaBootstrap',
            $this->tester->getDisplay()
        );
    }

    /**
     * Each @see form renders as written, a URL becomes a link, and a bare "@see"
     * names nothing, so it must not leave an empty reference behind. A wrapped
     * line continues the reference's own description, as a one-line one does.
     */
    public function testSeeReferencesRenderUnderTheClassTitle(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/See', '--see' => true]);
        $output = $this->tester->getDisplay();

        $this->assertStringContainsString(
            '> Points readers elsewhere.' . PHP_EOL . PHP_EOL
            . 'See [The guide](https://example.com/guide)<br />' . PHP_EOL
            . 'See <https://example.com/plain><br />' . PHP_EOL
            . 'See \\Acme\\Fixtures\\See\\References::act() for how it acts, which takes a second line to say.<br />'
            . PHP_EOL
            . PHP_EOL
            . '| Visibility',
            $output
        );
    }

    public function testSeeReferencesRenderOnMethods(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/See', '--see' => true]);

        $this->assertStringContainsString(
            '<em>&nbsp;&nbsp;&nbsp;&nbsp;See: [Method notes](https://example.com/method)</em>',
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

    public function testSeeReferencesAreLeftOutWithoutTheOption(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/See']);

        $this->assertStringNotContainsString('https://example.com', $this->tester->getDisplay());
    }

    public function testIgnoredClassesAreNeverDocumented(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Tags']);
        $output = $this->tester->getDisplay();

        $this->assertStringNotContainsString('Tags\\Ignored', $output);
        $this->assertStringContainsString('### Class: \\Acme\\Fixtures\\Tags\\InternalClass', $output);
        $this->assertStringContainsString('<strong>internalHelper()</strong>', $output);
    }

    public function testNoInternalLeavesOutInternalClassesAndMethods(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Tags', '--no-internal' => true]);
        $output = $this->tester->getDisplay();

        $this->assertStringNotContainsString('InternalClass', $output);
        $this->assertStringNotContainsString('internalHelper', $output);
        $this->assertStringContainsString('<strong>published()</strong>', $output);
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

    public function testMethodRegexLimitsTheDocumentedMethods(): void
    {
        $this->tester->execute([
            'class'         => 'Acme\\Fixtures\\Links\\User',
            '--methodRegex' => '/^makeBar/',
        ]);
        $output = $this->tester->getDisplay();

        $this->assertStringContainsString('<strong>makeBar()</strong>', $output);
        $this->assertStringContainsString('<strong>makeBarBaz()</strong>', $output);
        $this->assertStringNotContainsString('makeEither', $output);
    }

    /**
     * A class in the global namespace has no namespace to show, so its title
     * carries the bare name.
     */
    public function testGlobalClassTitleCarriesTheBareName(): void
    {
        $this->tester->execute([
            'class'       => 'GlobalScopedFixture',
            '--bootstrap' => __DIR__ . '/Acme/Fixtures/Global/GlobalScopedFixture.php',
        ]);

        $this->assertStringContainsString(
            '### Class: GlobalScopedFixture' . PHP_EOL,
            $this->tester->getDisplay()
        );
    }
}
