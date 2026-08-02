<?php

declare(strict_types=1);

namespace PHPDocsMD;

use ReflectionClass;

use function count;
use function in_array;
use function is_array;

/**
 * Class that can extract all use statements in a file
 *
 * @package PHPDocsMD
 */
class UseInspector
{
    /**
     * Tokens that carry no meaning between a `use` keyword and the name it imports.
     */
    private const SKIPPABLE = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    public function getUseStatements(ReflectionClass $reflectionClass): array
    {
        $classUseStatements = [];
        $classFile = $reflectionClass->getFileName();
        if ($classFile) {
            $classUseStatements = $this->getUseStatementsInFile($classFile);
        }

        return $classUseStatements;
    }

    public function getUseStatementsInFile(string $filePath): array
    {
        return $this->getUseStatementsInString((string)file_get_contents($filePath));
    }

    /**
     * Collect the classes a piece of PHP imports.
     *
     * Keyed by the name the importing file actually writes — the alias when there
     * is one, the last segment otherwise — because that is the name a docblock
     * type has to be looked up by.
     *
     * Tokenising rather than splitting on the string "use" is what keeps prose
     * ("because"), closure captures (`function () use ($x)`) and comment text out
     * of the result, and what makes aliases and group imports resolvable:
     *
     *   use A\B;             -> ['B' => '\A\B']
     *   use A\B as C;        -> ['C' => '\A\B']
     *   use A\{B, C};        -> ['B' => '\A\B', 'C' => '\A\C']
     *   use function strlen; -> skipped   (not a class)
     *
     * @return array<string, string>
     */
    public function getUseStatementsInString(string $content): array
    {
        $usages = [];
        $tokens = token_get_all($content);
        $depth = 0;
        $classBody = [];        // brace depths that are class/interface/trait/enum bodies
        $pendingClass = false;

        for ($i = 0, $len = count($tokens); $i < $len; $i++) {
            $token = $tokens[$i];

            // "{$var}" and "${var}" inside a string open a brace the tokenizer
            // reports as an array token, but close it with a bare "}" that the
            // arm below does count. Left uncounted, the depth sinks by one per
            // interpolation and unset($classBody[$depth]) then clears the marker
            // for a body still being read — after which a trait import in that
            // body is taken for a file-level one and overwrites the real import.
            if (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
                $depth++;
                continue;
            }
            if ($token === '{') {
                $depth++;
                if ($pendingClass) {
                    $classBody[$depth] = true;
                    $pendingClass = false;
                }
                continue;
            }
            if ($token === '}') {
                unset($classBody[$depth]);
                $depth--;
                continue;
            }
            if (!is_array($token)) {
                continue;
            }
            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                // "Foo::class" is also T_CLASS and opens no body.
                $pendingClass = $this->previousMeaningfulText($tokens, $i) !== '::';
                continue;
            }
            if ($token[0] !== T_USE) {
                continue;
            }
            // A trait import inside a class body is not a file-level import. It
            // used to be read as one and written to the map unqualified, which
            // overwrote the real import of the same name — the file-level
            // "use Vendor\Timestamps;" became "\Timestamps" as soon as the class
            // body said "use Timestamps;".
            //
            // A closure's capture list is the one `use` preceded by ")".
            if (isset($classBody[$depth]) || $this->previousMeaningfulText($tokens, $i) === ')') {
                continue;
            }
            foreach ($this->readImport($tokens, $i) as $localName => $import) {
                $usages[$localName] = Utils::sanitizeClassName($import);
            }
        }

        return $usages;
    }

    /**
     * Text of the nearest preceding token that carries meaning, or '' at the
     * start of the file.
     */
    private function previousMeaningfulText(array $tokens, int $index): string
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], self::SKIPPABLE, true)) {
                continue;
            }

            return is_array($token) ? $token[1] : $token;
        }

        return '';
    }

    /**
     * Read one import statement, expanding a group import into its members.
     *
     * @return array<string, string> local name => imported name
     */
    private function readImport(array $tokens, int $index): array
    {
        $state = ['prefix' => '', 'name' => '', 'alias' => '', 'readingAlias' => false, 'notAClass' => false];
        $imports = [];

        for ($i = $index + 1, $len = count($tokens); $i < $len; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                if (in_array($token[0], self::SKIPPABLE, true)) {
                    continue;
                }
                // `use function foo;` / `use const BAR;` import no class.
                if (in_array($token[0], [T_FUNCTION, T_CONST], true)) {
                    $state['notAClass'] = true;
                    continue;
                }
                if ($token[0] === T_AS) {
                    $state['readingAlias'] = true;
                    continue;
                }
                if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    $key = $state['readingAlias'] ? 'alias' : 'name';
                    $state[$key] .= $token[1];
                    continue;
                }
                if ($token[0] === T_NS_SEPARATOR) {
                    $state['name'] .= '\\';
                    continue;
                }

                break;
            }

            if ($token === '{') {
                $state['prefix'] = rtrim($state['name'], '\\') . '\\';
                $state['name'] = '';
                continue;
            }

            if ($token === ',' || $token === '}') {
                $this->flush($imports, $state);
                if ($token === '}') {
                    $state['prefix'] = '';
                }
                continue;
            }

            if ($token === ';') {
                break;
            }

            break;
        }

        $this->flush($imports, $state);

        return $imports;
    }

    /**
     * @param array<string, string>       $imports
     * @param array<string, string|bool>  $state
     */
    private function flush(array &$imports, array &$state): void
    {
        $name = trim((string)$state['name']);
        $alias = trim((string)$state['alias']);
        $notAClass = (bool)$state['notAClass'];

        $state['name'] = '';
        $state['alias'] = '';
        $state['readingAlias'] = false;
        $state['notAClass'] = false;

        if ($name === '' || $notAClass) {
            return;
        }

        $imported = $state['prefix'] . $name;
        $segments = explode('\\', $imported);
        $localName = $alias !== '' ? $alias : (string)end($segments);

        $imports[$localName] = $imported;
    }
}
