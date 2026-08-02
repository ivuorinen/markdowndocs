<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use PHPDocsMD\Entities\FunctionEntity;
use PHPDocsMD\Entities\ParamEntity;
use PHPDocsMD\MDTableGenerator;
use PHPUnit\Framework\TestCase;

class MDTableGeneratorTest extends TestCase
{
    public function testDeprecatedFunc(): void
    {
        $tbl = new MDTableGenerator();
        $tbl->openTable();

        $deprecated = new FunctionEntity();
        $deprecated->isDeprecated(true);
        $deprecated->setDeprecationMessage('Is deprecated');
        $deprecated->setName('myFunc');
        $deprecated->setReturnType('mixed');

        $this->assertTrue($deprecated->isDeprecated());

        $tbl->addFunc($deprecated);

        $tblMarkdown = $tbl->getTable();
        $expect = trim(' ') .
            '| Visibility | Function |' . PHP_EOL .
            '|:-----------|:---------|' . PHP_EOL .
            '| public | <del><strong>myFunc()</strong> : <em>mixed</em></del>' .
            '<br /><em>DEPRECATED - Is deprecated</em> |';

        $this->assertEquals($expect, $tblMarkdown);
    }

    public function testFunc(): void
    {
        $tbl = new MDTableGenerator();
        $tbl->openTable();

        $func = new FunctionEntity();
        $func->setName('myFunc');
        $tbl->addFunc($func);

        $tblMarkdown = $tbl->getTable();
        $expect = '| Visibility | Function |' . PHP_EOL .
            '|:-----------|:---------|' . PHP_EOL .
            '| public | <strong>myFunc()</strong> : <em>void</em> |';

        $this->assertEquals($expect, $tblMarkdown);
    }

    public function testFuncWithAllFeatures(): void
    {
        $tbl = new MDTableGenerator();
        $tbl->openTable();

        $func = new FunctionEntity();

        $this->assertFalse($func->isStatic());
        $this->assertFalse($func->hasParams());
        $this->assertFalse($func->isDeprecated());
        $this->assertFalse($func->isAbstract());
        $this->assertEquals('public', $func->getVisibility());

        $func->isStatic(true);
        $func->setVisibility('protected');
        $func->setName('someFunc');
        $func->setDescription('desc...');
        $func->setReturnType('\\stdClass');

        $params = [];

        $paramA = new ParamEntity();
        $paramA->setName('$var');
        $paramA->setType('mixed');
        $paramA->setDefault('null');
        $params[] = $paramA;

        $paramB = new ParamEntity();
        $paramB->setName('$other');
        $paramB->setType('string');
        $paramB->setDefault("'test'");
        $params[] = $paramB;

        $func->setParams($params);

        $tbl->addFunc($func);

        $this->assertTrue($func->isStatic());
        $this->assertTrue($func->hasParams());
        $this->assertEquals('protected', $func->getVisibility());

        $tblMarkdown = $tbl->getTable();
        // The closing ")" is inside the last parameter's <strong>: the generator
        // re-opens before it so the trailing closer has something to close, and
        // the "</strong><strong>" collapse below merges the pair. This used to
        // read "…$other='test'</strong>)</strong>", one closer too many.
        $expect = '| Visibility | Function |' . PHP_EOL .
            '|:-----------|:---------|' . PHP_EOL .
            '| protected static | <strong>someFunc(</strong><em>mixed</em> <strong>$var=null</strong>, '
                  . '<em>string</em> <strong>$other=\'test\')</strong> : <em>\\stdClass</em><br />'
                  . '<em>desc...</em> |';

        $this->assertEquals($expect, $tblMarkdown);
    }

    /**
     * Two thirds of every generated document was invalid HTML: the generator
     * opened one <strong>, closed it after the function name when there were
     * parameters, and closed it again after the argument list.
     */
    public function testEveryRowBalancesItsMarkupTags(): void
    {
        $withParams = new FunctionEntity();
        $withParams->setName('takesArgs');
        $withParams->setReturnType('void');
        $param = new ParamEntity();
        $param->setName('$a');
        $param->setType('int');
        $withParams->setParams([$param]);

        $withoutParams = new FunctionEntity();
        $withoutParams->setName('takesNothing');
        $withoutParams->setReturnType('string');

        $tbl = new MDTableGenerator();
        $tbl->openTable();

        foreach ([$withParams, $withoutParams] as $func) {
            $row = $tbl->addFunc($func);

            self::assertSame(
                substr_count($row, '<strong>'),
                substr_count($row, '</strong>'),
                "unbalanced <strong> in: $row"
            );
            self::assertSame(
                substr_count($row, '<em>'),
                substr_count($row, '</em>'),
                "unbalanced <em> in: $row"
            );
        }
    }

    /**
     * A markdown table row must be one line. A default value containing a
     * newline — "--- Original\n+++ New\n" is a real one, from sebastian/diff —
     * used to break out of the row and truncate the whole table.
     */
    public function testANewlineInADefaultValueCannotBreakTheRow(): void
    {
        $param = new ParamEntity();
        $param->setName('$header');
        $param->setType('string');
        $param->setDefault("`'--- Original\n+++ New\n'`");

        $func = new FunctionEntity();
        $func->setName('__construct');
        $func->setReturnType('void');
        $func->setParams([$param]);

        $tbl = new MDTableGenerator();
        $tbl->openTable();
        $row = $tbl->addFunc($func);

        self::assertStringNotContainsString("\n", $row, 'the row must occupy exactly one line');
        self::assertStringContainsString('<br />', $row, 'the newline should survive as a line break');
    }

    /**
     * A bare "@deprecated" carries no message; " - " with nothing after it reads
     * as a truncated sentence.
     */
    public function testDeprecationWithoutAMessageHasNoDanglingSeparator(): void
    {
        $func = new FunctionEntity();
        $func->setName('old');
        $func->setReturnType('void');
        $func->isDeprecated(true);

        $tbl = new MDTableGenerator();
        $tbl->openTable();
        $row = $tbl->addFunc($func);

        self::assertStringContainsString('<em>DEPRECATED</em>', $row);
        self::assertStringNotContainsString('DEPRECATED - ', $row);
    }

    public static function exampleComments(): array
    {
        return [
            'three-space indent is stripped' => ["\n   line one\n   line two", "```\nline one\nline two\n```"],
            'four-space indent is stripped'  => ["\n    line one\n    line two", "```\nline one\nline two\n```"],
            'seven-space indent is stripped' => ["\n       line one\n       line two", "```\nline one\nline two\n```"],
            'php is detected'                => ["\n   <?php\n   \$x = 1;", "```php\n<?php\n\$x = 1;\n```"],
            'js is detected'                 => ["\n   var x = 1;", "```js\nvar x = 1;\n```"],
            // "var" inside markup is not JavaScript
            'markup is not js'               => ["\n   var x = 1;\n   </div>", "```\nvar x = 1;\n</div>\n```"],
        ];
    }

    /**
     * @dataProvider exampleComments
     */
    public function testFormatExampleComment(string $input, string $expected): void
    {
        $this->assertEquals($expected, MDTableGenerator::formatExampleComment($input));
    }

    /**
     * The old heuristic matched the *deepest* line's indentation and stripped
     * that many spaces from every line that had them, which rendered a nested
     * block shallower than the block containing it.
     */
    public function testNestedIndentationInAnExampleIsPreserved(): void
    {
        $example = "\n    foreach (\$items as \$item) {\n"
            . "        if (\$item->ok()) {\n"
            . "            echo \$item->name();\n"
            . "        }\n"
            . "    }";

        $rendered = MDTableGenerator::formatExampleComment($example);

        $this->assertEquals(
            "```\nforeach (\$items as \$item) {\n"
            . "    if (\$item->ok()) {\n"
            . "        echo \$item->name();\n"
            . "    }\n"
            . "}\n```",
            $rendered
        );
    }

    /**
     * An @example written in the markdown style carries its own fence, which
     * closed the wrapper on its opening line and left the example rendering as
     * prose between two empty code blocks.
     */
    public function testAnExampleContainingAFenceGetsALongerFence(): void
    {
        $rendered = MDTableGenerator::formatExampleComment("```\n\$x = [1, 2, 3];\n```");

        $this->assertEquals("````\n```\n\$x = [1, 2, 3];\n```\n````", $rendered);
        $this->assertSame(
            0,
            substr_count($rendered, "\n```\n") % 2,
            'the fences inside the block must stay balanced'
        );
    }

    public static function codeTaggedComments(): array
    {
        return [
            'bare code tag'          => ["<code>\n   \$x = 1;\n</code>", "```\n\$x = 1;\n```"],
            // The opening tag may carry attributes; this used to swallow the example whole
            'code tag with class'    => ["<code class=\"php\">\n   \$x = 1;\n</code>", "```\n\$x = 1;\n```"],
            'code tag with id'       => ["<code id='a' data-x='1'>\n   \$x = 1;\n</code>", "```\n\$x = 1;\n```"],
            'last block wins'        => ['<code>first</code><code>second</code>', "```\nsecond\n```"],
            'unclosed opening tag'   => ["<code>\n   \$x = 1;", "```\n\$x = 1;\n```"],
        ];
    }

    /**
     * @dataProvider codeTaggedComments
     */
    public function testCodeTagsAreStripped(string $input, string $expected): void
    {
        $this->assertEquals($expected, MDTableGenerator::formatExampleComment($input));
    }

    public function testExamplesAreAppendedAfterTheTable(): void
    {
        $tbl = new MDTableGenerator();
        $tbl->openTable();

        $func = new FunctionEntity();
        $func->setName('myFunc');
        $func->setClass('\\Acme\\Widget');
        $func->setExample("\n   <?php\n   \$x = 1;");
        $tbl->addFunc($func);

        $markdown = $tbl->getTable();

        $this->assertStringContainsString('###### Examples of Widget::myFunc()', $markdown);
        $this->assertStringContainsString("```php\n<?php\n\$x = 1;\n```", $markdown);
    }

    public function testAppendingExamplesCanBeDisabled(): void
    {
        $tbl = new MDTableGenerator();
        $tbl->appendExamplesToEndOfTable(false);
        $tbl->openTable();

        $func = new FunctionEntity();
        $func->setName('myFunc');
        $func->setClass('\\Acme\\Widget');
        $func->setExample("\n   <?php\n   \$x = 1;");
        $tbl->addFunc($func);

        $this->assertStringNotContainsString('###### Examples', $tbl->getTable());
    }

    public function testSeeEntriesAreRenderedOnlyWhenRequested(): void
    {
        $func = new FunctionEntity();
        $func->setName('myFunc');
        $func->setSee(['<https://example.test>', 'SomeClass::other()']);

        $off = new MDTableGenerator();
        $off->openTable();
        $off->addFunc($func);
        $this->assertStringNotContainsString('See:', $off->getTable());

        $on = new MDTableGenerator();
        $on->openTable();
        $on->addFunc($func, true);
        $row = $on->getTable();
        // <url> is markdown autolink syntax produced by DocInfoExtractor, left as-is
        $this->assertStringContainsString('See: <https://example.test>', $row);
        $this->assertStringContainsString('SomeClass::other()', $row);
    }

    /**
     * A pipe in any interpolated value would otherwise add a column to the row.
     */
    public function testPipesInValuesAreEscaped(): void
    {
        $tbl = new MDTableGenerator();
        $tbl->openTable();

        $param = new ParamEntity();
        $param->setName('$either');
        $param->setType('int|string');

        $func = new FunctionEntity();
        $func->setName('myFunc');
        $func->setReturnType('\\A|\\B');
        $func->setParams([$param]);
        $tbl->addFunc($func);

        $row = explode(PHP_EOL, $tbl->getTable())[2];

        $this->assertStringContainsString('int\\|string', $row);
        $this->assertStringContainsString('\\A\\|\\B', $row);
        $this->assertEquals(3, substr_count(str_replace('\\|', '', $row), '|'));
    }

    public function testToggleDeclaringAbstraction(): void
    {
        $tbl = new MDTableGenerator();
        $tbl->openTable();

        $func = new FunctionEntity();
        $func->isAbstract(true);
        $func->setName('someFunc');

        $tbl->addFunc($func);
        $tblMarkdown = $tbl->getTable();
        $expect = '| Visibility | Function |' . PHP_EOL .
            '|:-----------|:---------|' . PHP_EOL .
            '| public | <strong>abstract someFunc()</strong> : <em>void</em> |';

        $this->assertEquals($expect, $tblMarkdown);

        $tbl->openTable();
        $tbl->doDeclareAbstraction(false);
        $tbl->addFunc($func);

        $tblMarkdown = $tbl->getTable();
        $expect = '| Visibility | Function |' . PHP_EOL .
            '|:-----------|:---------|' . PHP_EOL .
            '| public | <strong>someFunc()</strong> : <em>void</em> |';

        $this->assertEquals($expect, $tblMarkdown);
    }
}
