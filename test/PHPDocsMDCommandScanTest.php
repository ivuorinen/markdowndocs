<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use InvalidArgumentException;

class PHPDocsMDCommandScanTest extends CommandTestCase
{
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
     * T_CLASS also marks "::class" and anonymous classes. Neither names a type,
     * so neither may stand in for the declaration that follows, and a file
     * holding only an anonymous class has nothing to document.
     */
    public function testAnonymousClassesAndClassConstantsAreNotDeclarations(): void
    {
        $this->tester->execute(['class' => __DIR__ . '/Acme/Fixtures/Anonymous']);
        $output = $this->tester->getDisplay();

        $this->assertStringContainsString('### Class: \\Acme\\Fixtures\\Anonymous\\AfterNamelessClasses', $output);
        $this->assertSame(1, substr_count($output, '### '));
    }

    /**
     * A file cut off mid-declaration — an unsaved buffer, a failed write —
     * declares nothing, and a tree holding nothing else has nothing to document.
     */
    public function testATruncatedFileDeclaresNothing(): void
    {
        $dir = sys_get_temp_dir() . '/phpdocsmd-truncated-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/Cut.php', "<?php\n\nnamespace Acme\\Cut;\n\nclass");

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('No classes found');

            $this->tester->execute(['class' => $dir]);
        } finally {
            unlink($dir . '/Cut.php');
            rmdir($dir);
        }
    }

    /**
     * A file the scan cannot read is reported and skipped. Its read failure
     * was a PHP warning printed to stdout, into the document, while the class
     * vanished without a word.
     */
    public function testAnUnreadableFileIsReportedAndKeptOutOfTheDocument(): void
    {
        $dir = sys_get_temp_dir() . '/phpdocsmd-unreadable-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/Readable.php', "<?php\n\nnamespace Acme\\Unreadable;\n\nclass Readable\n{\n}\n");
        file_put_contents($dir . '/Locked.php', "<?php\n\nnamespace Acme\\Unreadable;\n\nclass Locked\n{\n}\n");
        chmod($dir . '/Locked.php', 0o000);

        try {
            if (is_readable($dir . '/Locked.php')) {
                $this->markTestSkipped('running with permission to read any file');
            }

            $this->tester->execute(
                ['class' => $dir, '--bootstrap' => $dir . '/Readable.php'],
                ['capture_stderr_separately' => true]
            );

            $this->assertStringContainsString('### Class: \\Acme\\Unreadable\\Readable', $this->tester->getDisplay());
            $this->assertStringNotContainsString('Locked', $this->tester->getDisplay());
            // The scan reports resolved paths.
            $this->assertStringContainsString(
                'phpdoc-md: skipping ' . realpath($dir) . '/Locked.php (cannot read the file)',
                $this->tester->getErrorOutput()
            );
        } finally {
            chmod($dir . '/Locked.php', 0o644);
            unlink($dir . '/Locked.php');
            unlink($dir . '/Readable.php');
            rmdir($dir);
        }
    }

    /**
     * Symbolic links are not followed — that keeps the scan inside the tree
     * and out of cycles — but each one is reported, so a linked package that
     * goes undocumented does not go unnoticed.
     */
    public function testASymbolicLinkIsReportedAndNotFollowed(): void
    {
        $dir = sys_get_temp_dir() . '/phpdocsmd-links-' . bin2hex(random_bytes(4));
        mkdir($dir . '/real', 0o777, true);
        file_put_contents($dir . '/real/Linked.php', "<?php\n\nnamespace Acme\\Links;\n\nclass Linked\n{\n}\n");
        symlink($dir . '/real/Linked.php', $dir . '/LinkedFile.php');
        symlink($dir . '/real', $dir . '/linked-dir');

        try {
            $this->tester->execute(
                ['class' => $dir, '--bootstrap' => $dir . '/real/Linked.php'],
                ['capture_stderr_separately' => true]
            );

            $this->assertStringContainsString(
                'phpdoc-md: skipping ' . $dir . '/LinkedFile.php (symbolic link, not followed)',
                $this->tester->getErrorOutput()
            );
            $this->assertStringContainsString(
                'phpdoc-md: skipping ' . $dir . '/linked-dir (symbolic link, not followed)',
                $this->tester->getErrorOutput()
            );
            // Documented once, through the real file only.
            $this->assertSame(1, substr_count($this->tester->getDisplay(), '### Class: \\Acme\\Links\\Linked'));
        } finally {
            unlink($dir . '/LinkedFile.php');
            unlink($dir . '/linked-dir');
            unlink($dir . '/real/Linked.php');
            rmdir($dir . '/real');
            rmdir($dir);
        }
    }
}
