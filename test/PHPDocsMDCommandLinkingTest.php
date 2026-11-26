<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

class PHPDocsMDCommandLinkingTest extends CommandTestCase
{
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
     * A type written with a class alias links to the section of the class the
     * alias names. The document has a section only under the class's own name.
     */
    public function testAClassAliasLinksToTheSectionOfItsClass(): void
    {
        $this->tester->execute([
            'class' => 'Acme\\Fixtures\\AliasLinks\\TakesAlias,Acme\\Fixtures\\AliasLinks\\TargetAlias',
        ]);

        $this->assertStringContainsString(
            ': <em>[\\Acme\\Fixtures\\AliasLinks\\TargetAlias](#class-acme-fixtures-aliaslinks-target)</em>',
            $this->tester->getDisplay()
        );
    }

    /**
     * An example is code as its author wrote it. Markup in it that matches a
     * type cell or an inheritance line is still part of the code, so it gets
     * no links, at class or method level.
     */
    public function testAnExampleIsNotLinked(): void
    {
        $this->tester->execute(['class' => 'Acme\\Fixtures\\Links\\ExampleMarkup,Acme\\Fixtures\\Links\\Bar']);

        $output = $this->tester->getDisplay();
        $this->assertStringContainsString("echo '<em>\\Acme\\Fixtures\\Links\\Bar</em>';", $output);
        $this->assertStringContainsString('*This class extends \\Acme\\Fixtures\\Links\\Bar*', $output);
        $this->assertStringContainsString("echo '<em>\\DateTime</em>';", $output);
        // Outside the examples, linking still runs.
        $this->assertStringContainsString(
            ': <em>[\\Acme\\Fixtures\\Links\\Bar](#interface-acme-fixtures-links-bar)</em>',
            $output
        );
    }
}
