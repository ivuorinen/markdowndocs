<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use PHPDocsMD\UseInspector;
use PHPUnit\Framework\TestCase;

class UseInspectorTest extends TestCase
{
    private UseInspector $inspector;

    protected function setUp(): void
    {
        $this->inspector = new UseInspector();
    }

    public function testInspection(): void
    {
        $code = <<<'PHP'
        <?php

        namespace apa;

        use apa\sten\groda;
        use  apa\sten\BjornGroda;
        use \apa\sten\groda;
        use \apa;
        use \apa\Sten
        ;

        useBala;
        PHP;

        $expected = [
            'groda'       => '\\apa\\sten\\groda',
            'BjornGroda'  => '\\apa\\sten\\BjornGroda',
            'apa'         => '\\apa',
            'Sten'        => '\\apa\\Sten',
        ];

        $this->assertEquals($expected, $this->inspector->getUseStatementsInString($code));
    }

    /**
     * The alias is the name the file writes, so it has to be the key — otherwise
     * "@return Short" has nothing to match against.
     */
    public function testAliasedImportIsKeyedByTheAlias(): void
    {
        $code = "<?php\nnamespace App;\nuse Vendor\\LongName as Short;\n";

        $this->assertEquals(
            ['Short' => '\\Vendor\\LongName'],
            $this->inspector->getUseStatementsInString($code)
        );
    }

    public function testGroupImportIsExpandedIntoItsMembers(): void
    {
        $code = "<?php\nnamespace App;\nuse Vendor\\{Alpha, Beta as B};\n";

        $this->assertEquals(
            ['Alpha' => '\\Vendor\\Alpha', 'B' => '\\Vendor\\Beta'],
            $this->inspector->getUseStatementsInString($code)
        );
    }

    public function testFunctionAndConstImportsAreNotClasses(): void
    {
        $code = "<?php\nnamespace App;\nuse function strlen;\nuse const PHP_EOL;\nuse Vendor\\Real;\n";

        $this->assertEquals(
            ['Real' => '\\Vendor\\Real'],
            $this->inspector->getUseStatementsInString($code)
        );
    }

    /**
     * "use" is a common English substring and a closure keyword. Neither may be
     * read as an import — splitting on the bare substring used to turn the prose
     * and the capture list below into three bogus class references.
     */
    public function testProseAndClosureCapturesAreNotImports(): void
    {
        $code = <<<'PHP'
        <?php

        namespace App;

        use Vendor\Real;

        /**
         * We do it this way because of reasons, and misuse nothing.
         */
        class Because
        {
            public function run(array $xs): array
            {
                $n = 1;
                // we do this because reasons
                return array_map(function ($x) use ($n) {
                    return $x + $n;
                }, $xs);
            }
        }
        PHP;

        $this->assertEquals(
            ['Real' => '\\Vendor\\Real'],
            $this->inspector->getUseStatementsInString($code)
        );
    }

    public function testNonPhpTextHasNoImports(): void
    {
        $this->assertEquals(
            [],
            $this->inspector->getUseStatementsInString('prose, because reasons; use whatever.')
        );
    }

    /**
     * A trait import inside a class body is not a file-level import. It was read
     * as one and written unqualified, so it overwrote the real import of the
     * same name — every docblock type resolved through that name then pointed at
     * a namespace that does not exist.
     */
    public function testATraitImportInAClassBodyDoesNotOverwriteTheFileImport(): void
    {
        $code = <<<'PHP'
        <?php

        namespace Acme;

        use Vendor\Timestamps;

        class Consumer
        {
            use Timestamps;
        }
        PHP;

        $this->assertEquals(
            ['Timestamps' => '\\Vendor\\Timestamps'],
            $this->inspector->getUseStatementsInString($code)
        );
    }

    public function testATraitImportAloneContributesNoImport(): void
    {
        $code = <<<'PHP'
        <?php

        namespace Acme;

        class Consumer
        {
            use SomeTrait;
        }
        PHP;

        $this->assertEquals([], $this->inspector->getUseStatementsInString($code));
    }

    /**
     * A trait conflict-resolution block opens braces, which readImport() reads as
     * the start of a group import.
     */
    public function testATraitConflictBlockContributesNoImport(): void
    {
        $code = <<<'PHP'
        <?php

        namespace Acme;

        use Vendor\Real;

        class Consumer
        {
            use A, B {
                A::foo insteadof B;
            }
        }
        PHP;

        $this->assertEquals(
            ['Real' => '\\Vendor\\Real'],
            $this->inspector->getUseStatementsInString($code)
        );
    }

    /**
     * "Foo::class" is also T_CLASS and opens no body, so it must not make the
     * next brace look like a class body.
     */
    public function testClassConstantDoesNotHideLaterImports(): void
    {
        $code = <<<'PHP'
        <?php

        namespace Acme;

        use Vendor\First;

        const MAP = [First::class => 1];

        use Vendor\Second;
        PHP;

        $this->assertEquals(
            ['First' => '\\Vendor\\First', 'Second' => '\\Vendor\\Second'],
            $this->inspector->getUseStatementsInString($code)
        );
    }
}
