<?php

declare(strict_types=1);

namespace PHPDocsMD;

use PHPDocsMD\Entities\FunctionEntity;

/**
 * Class that can create a markdown-formatted table describing class functions
 * referred to via FunctionEntity objects
 *
 * @example
 * <code>
 *  <?php
 *      $generator = new PHPDocsMD\MDTableGenerator();
 *      $generator->openTable();
 *      foreach($classEntity->getFunctions() as $func) {
 *              $generator->addFunc( $func );
 *      }
 *      echo $generator->getTable();
 * </code>
 *
 * @package PHPDocsMD
 */
class MDTableGenerator implements TableGenerator
{
    private string $fullClassName = '';
    private string $markdown = '';
    private array $examples = [];
    private bool $appendExamples = true;
    private bool $declareAbstraction = true;

    /**
     * All example comments found while generating the table will be
     * appended to the end of the table. Setting $toggle to false
     * prevents this behaviour.
     */
    #[\Override]
    public function appendExamplesToEndOfTable(bool $toggle): void
    {
        $this->appendExamples = $toggle;
    }

    /**
     * Begin generating a new markdown-formatted table
     */
    #[\Override]
    public function openTable(): void
    {
        $this->examples = [];
        $this->markdown = ''; // Clear table
        $this->declareAbstraction = true;
        $this->add('| Visibility | Function |');
        $this->add('|:-----------|:---------|');
    }

    private function add(string $str): void
    {
        $this->markdown .= $str . PHP_EOL;
    }

    /**
     * An unescaped pipe terminates the cell it sits in, so a union type such as
     * "int | string" would silently add a column to the row. A newline
     * terminates the whole row, so a multi-line default value — "--- Original\n
     * +++ New\n" is a real one, from sebastian/diff — used to truncate the table
     * at that row and render everything after it as loose text.
     */
    private static function escapeCell(string $str): string
    {
        return str_replace(
            ['|', "\r\n", "\r", "\n"],
            ['\\|', '<br />', '<br />', '<br />'],
            $str
        );
    }

    /**
     * Toggle whether methods being abstract (or part of an interface)
     * should be declared as abstract in the table
     */
    #[\Override]
    public function doDeclareAbstraction(bool $toggle): void
    {
        $this->declareAbstraction = $toggle;
    }

    /**
     * Generates a markdown formatted table row with information about given function. Then adds the
     * row to the table and returns the markdown formatted string.
     */
    #[\Override]
    public function addFunc(FunctionEntity $func, bool $includeSee = false): string
    {
        $this->fullClassName = $func->getClass();

        $str = '<strong>';

        if ($this->declareAbstraction && $func->isAbstract()) {
            $str .= 'abstract ';
        }

        $str .= self::escapeCell($func->getName()) . '(';

        if ($func->hasParams()) {
            $params = [];
            foreach ($func->getParams() as $param) {
                $paramStr = '<em>' . self::escapeCell($param->getType()) . '</em> <strong>' .
                    self::escapeCell($param->getName());
                if ($param->hasDefault()) {
                    $paramStr .= '=' . self::escapeCell($param->getDefault());
                }
                $paramStr .= '</strong>';
                $params[] = $paramStr;
            }
            // Re-open before the ")" so the closing tag appended below has
            // something to close. Without it every row with parameters carried
            // an unmatched </strong>.
            $str .= '</strong>' . implode(', ', $params) . '<strong>';
        }

        $str .= ')</strong> : <em>' . self::escapeCell($func->getReturnType()) . '</em>';

        if ($func->isDeprecated()) {
            $message = self::escapeCell($func->getDeprecationMessage());
            $str = '<del>' . $str . '</del>';
            // A bare "@deprecated" carries no message; " - " with nothing after
            // it reads as a truncated sentence.
            $str .= $message === ''
                ? '<br /><em>DEPRECATED</em>'
                : '<br /><em>DEPRECATED - ' . $message . '</em>';
        } elseif ($func->getDescription()) {
            $str .= '<br /><em>' . self::escapeCell($func->getDescription()) . '</em>';
        }
        if ($includeSee && $func->getSee()) {
            $str .= '<br /><em>&nbsp;&nbsp;&nbsp;&nbsp;See: ' .
                self::escapeCell(implode(', ', $func->getSee())) . '</em>';
        }

        $str = str_replace(
            ['</strong><strong>', '</strong></strong> '],
            ['', '</strong>'],
            trim($str)
        );

        if ($func->getExample()) {
            $this->examples[$func->getName()] = $func->getExample();
        }

        $firstCol = $func->getVisibility() . ($func->isStatic() ? ' static' : '');
        $markDown = '| ' . $firstCol . ' | ' . $str . ' |';

        $this->add($markDown);

        return $markDown;
    }

    #[\Override]
    public function getTable(): string
    {
        $tbl = trim($this->markdown);
        if ($this->appendExamples && !empty($this->examples)) {
            $className = Utils::getClassBaseName($this->fullClassName);
            foreach ($this->examples as $funcName => $example) {
                $tbl .= sprintf(
                    "\n###### Examples of %s::%s()\n%s",
                    $className,
                    $funcName,
                    self::formatExampleComment($example)
                );
            }
        }

        return $tbl;
    }

    /**
     * Create a markdown-formatted code view out of an example comment
     */
    #[\Override]
    public static function formatExampleComment(string $example): string
    {
        // Remove possible code tag
        $example = trim(self::dedent(self::stripCodeTags($example)));

        $type = '';

        // A very naive analysis of the programming language used in the comment
        if (str_contains($example, '<?php')) {
            $type = 'php';
        } elseif (str_contains($example, 'var ') && !str_contains($example, '</')) {
            $type = 'js';
        }

        // The fence has to be longer than any fence inside the example. An
        // @example written in the markdown style carries its own ```, which
        // closed this one on its opening line and left the example rendering as
        // prose between two empty code blocks.
        $longest = preg_match_all('/`{3,}/', $example, $matches)
            ? max(array_map('strlen', $matches[0]))
            : 0;
        $fence = str_repeat('`', max(3, $longest + 1));

        return sprintf("%s%s\n%s\n%s", $fence, $type, $example, $fence);
    }

    /**
     * Remove the indentation every non-blank line shares.
     *
     * The width has to be the minimum across the example, not the maximum. The
     * old heuristic looked for 7, then 4, then 3 leading spaces and stripped
     * that many from whichever lines happened to have them, which rendered a
     * nested block *shallower* than the block containing it.
     */
    private static function dedent(string $text): string
    {
        $indent = null;
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            $width = strlen($line) - strlen(ltrim($line, ' '));
            $indent = $indent === null ? $width : min($indent, $width);
        }

        return $indent ? (string)preg_replace('/^ {' . $indent . '}/m', '', $text) : $text;
    }

    private static function stripCodeTags(string $example): string
    {
        if (!str_contains($example, '<code')) {
            return $example;
        }

        // The opening tag may carry attributes (<code class="php">), so match it
        // as a pattern rather than splitting on the literal "<code>". Keep the
        // last complete block, which is the long-standing behaviour here.
        preg_match_all('#<code[^>]*>(.*?)</code>#s', $example, $matches);
        $blocks = $matches[1];
        if ($blocks !== []) {
            return (string)end($blocks);
        }

        // Opening tag with no closing tag: keep everything after it.
        return (string)preg_replace('#^.*?<code[^>]*>#s', '', $example);
    }
}
