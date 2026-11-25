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
final class UseInspector
{
    /**
     * Tokens that carry no meaning between a `use` keyword and the name it imports.
     */
    private const array SKIPPABLE = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    /**
     * The classes the file declaring this class imports, keyed by the name the
     * file writes them under; empty for a class with no file (an internal one).
     *
     * @return array<string, string> local name => `\Fully\Qualified` name
     */
    public function getUseStatements(ReflectionClass $reflectionClass): array
    {
        $classUseStatements = [];
        $classFile = $reflectionClass->getFileName();
        if ($classFile !== false) {
            $classUseStatements = $this->getUseStatementsInFile($classFile);
        }

        return $classUseStatements;
    }

    /**
     * The classes a PHP file imports, as getUseStatementsInString() reads them;
     * empty when the file cannot be read.
     *
     * @return array<string, string> local name => `\Fully\Qualified` name
     */
    public function getUseStatementsInFile(string $filePath): array
    {
        // A file that cannot be read has no imports to report: false becomes "",
        // which imports nothing. The @ keeps PHP from printing the failure — the
        // caller is usually writing a document to stdout, and the warning
        // landed in it.
        return $this->getUseStatementsInString((string)@file_get_contents($filePath));
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
        // classBody: the brace depths that are class/interface/trait/enum bodies
        $scope = ['depth' => 0, 'classBody' => [], 'pendingClass' => false];

        for ($i = 0, $len = count($tokens); $i < $len; $i++) {
            if ($this->trackScope($tokens, $i, $scope) || !$this->isFileImport($tokens, $i, $scope)) {
                continue;
            }
            foreach ($this->readImport($tokens, $i) as $localName => $import) {
                $usages[$localName] = Utils::sanitizeClassName($import);
            }
        }

        return $usages;
    }

    /**
     * Follow the braces and the class-like declarations, so that a `use`
     * can be told apart from a trait import in a class body. Returns whether
     * the token was one of them.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens token_get_all() output
     * @param array{depth: int, classBody: array<int, true>, pendingClass: bool} $scope
     */
    private function trackScope(array $tokens, int $index, array &$scope): bool
    {
        $token = $tokens[$index];

        // "{$var}" and "${var}" inside a string open a brace the tokenizer
        // reports as an array token, but close it with a bare "}" that the
        // arm below does count. Left uncounted, the depth sinks by one per
        // interpolation and unset($classBody[$depth]) then clears the marker
        // for a body still being read — after which a trait import in that
        // body is taken for a file-level one and overwrites the real import.
        if (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
            $scope['depth']++;

            return true;
        }
        if ($token === '{') {
            $scope['depth']++;
            if ($scope['pendingClass']) {
                $scope['classBody'][$scope['depth']] = true;
                $scope['pendingClass'] = false;
            }

            return true;
        }
        if ($token === '}') {
            unset($scope['classBody'][$scope['depth']]);
            $scope['depth']--;

            return true;
        }
        if (is_array($token) && in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            // "Foo::class" is also T_CLASS and opens no body.
            $scope['pendingClass'] = $this->previousMeaningfulText($tokens, $index) !== '::';

            return true;
        }

        return false;
    }

    /**
     * Whether the token is a `use` that imports into the file.
     *
     * A trait import inside a class body is not a file-level import. It used
     * to be read as one and written to the map unqualified, which overwrote
     * the real import of the same name — the file-level
     * "use Vendor\Timestamps;" became "\Timestamps" as soon as the class body
     * said "use Timestamps;".
     *
     * A closure's capture list is the one `use` preceded by ")".
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens token_get_all() output
     * @param array{depth: int, classBody: array<int, true>, pendingClass: bool} $scope
     */
    private function isFileImport(array $tokens, int $index, array $scope): bool
    {
        $token = $tokens[$index];

        return is_array($token)
            && $token[0] === T_USE
            && !isset($scope['classBody'][$scope['depth']])
            && $this->previousMeaningfulText($tokens, $index) !== ')';
    }

    /**
     * Text of the nearest preceding token that carries meaning.
     *
     * The walk cannot run off the front: token_get_all() always opens with the
     * open tag or inline HTML, neither of which is skippable, and that first
     * token is never the T_USE or T_CLASS this is asked about.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens token_get_all() output
     */
    private function previousMeaningfulText(array $tokens, int $index): string
    {
        $i = $index - 1;
        while (is_array($tokens[$i]) && in_array($tokens[$i][0], self::SKIPPABLE, true)) {
            $i--;
        }

        return is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
    }

    /**
     * Read one import statement, expanding a group import into its members.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens token_get_all() output
     *
     * @return array<string, string> local name => imported name
     */
    private function readImport(array $tokens, int $index): array
    {
        $state = ['prefix' => '', 'name' => '', 'alias' => '', 'readingAlias' => false, 'notAClass' => false];
        $imports = [];

        for ($i = $index + 1, $len = count($tokens); $i < $len; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && $this->readNameToken($token, $state)) {
                continue;
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

            // ";" ends the import. So does any token an import cannot contain:
            // only a file mid-edit holds one, and reading on would fold the
            // next statement's names into the imported one.
            break;
        }

        $this->flush($imports, $state);

        return $imports;
    }

    /**
     * Take one array token of an import into the reader's state: a part of
     * the imported name or its alias, `as`, or the `function`/`const` that
     * makes it no class import. Returns whether the token was one of those.
     *
     * @param array{0: int, 1: string, 2: int} $token
     * @param array{
     *     prefix: string,
     *     name: string,
     *     alias: string,
     *     readingAlias: bool,
     *     notAClass: bool
     * } $state
     */
    private function readNameToken(array $token, array &$state): bool
    {
        if (in_array($token[0], self::SKIPPABLE, true)) {
            return true;
        }
        // `use function foo;` / `use const BAR;` import no class.
        if (in_array($token[0], [T_FUNCTION, T_CONST], true)) {
            $state['notAClass'] = true;

            return true;
        }
        if ($token[0] === T_AS) {
            $state['readingAlias'] = true;

            return true;
        }
        if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            $key = $state['readingAlias'] ? 'alias' : 'name';
            $state[$key] .= $token[1];

            return true;
        }
        if ($token[0] === T_NS_SEPARATOR) {
            $state['name'] .= '\\';

            return true;
        }

        return false;
    }

    /**
     * @param array<string, string> $imports
     * @param array{
     *     prefix: string,
     *     name: string,
     *     alias: string,
     *     readingAlias: bool,
     *     notAClass: bool
     * } $state
     */
    private function flush(array &$imports, array &$state): void
    {
        $name = trim($state['name']);
        $alias = trim($state['alias']);
        $notAClass = $state['notAClass'];

        $state['name'] = '';
        $state['alias'] = '';
        $state['readingAlias'] = false;
        $state['notAClass'] = false;

        if ($name === '' || $notAClass) {
            return;
        }

        $imported = $state['prefix'] . $name;
        $segments = explode('\\', $imported);
        $localName = $alias !== '' ? $alias : end($segments);

        $imports[$localName] = $imported;
    }
}
