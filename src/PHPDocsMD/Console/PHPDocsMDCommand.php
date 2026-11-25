<?php

declare(strict_types=1);

namespace PHPDocsMD\Console;

use FilesystemIterator;
use InvalidArgumentException;
use PHPDocsMD\Entities\ClassEntity;
use PHPDocsMD\MDTableGenerator;
use PHPDocsMD\Reflections\Reflector;
use PHPDocsMD\TableGenerator;
use PHPDocsMD\Utils;
use ReflectionClass;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command used to extract markdown-formatted documentation from classes
 *
 * @package PHPDocsMD\Console
 */
class PHPDocsMDCommand extends \Symfony\Component\Console\Command\Command
{
    public const ARG_CLASS = 'class';
    public const OPT_BOOTSTRAP = 'bootstrap';
    public const OPT_IGNORE = 'ignore';
    public const OPT_VISIBILITY = 'visibility';
    public const OPT_METHOD_REGEX = 'methodRegex';
    public const OPT_TABLE_GENERATOR = 'tableGenerator';
    public const OPT_SEE = 'see';
    public const OPT_NO_INTERNAL = 'no-internal';
    public const OPT_NO_EXAMPLES = 'no-examples';

    /**
     * @var array
     */
    private array $memory = [];

    /**
     * @var array
     */
    private array $visibilityFilter = [];

    /**
     * @var string
     */
    private string $methodRegex = '';

    /**
     * Where diagnostics go. Writing to STDERR directly bypassed --quiet and made
     * the one message this tool emits invisible to CommandTester, so it printed
     * into the middle of the test runner's progress bar instead.
     */
    private ?OutputInterface $errorOutput = null;

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('generate')
            ->setDescription('Get docs for given class/source directory)')
            ->addArgument(
                self::ARG_CLASS,
                InputArgument::REQUIRED,
                'Class or source directory'
            )
            ->addOption(
                self::OPT_BOOTSTRAP,
                'b',
                InputOption::VALUE_REQUIRED,
                'File to be included before generating documentation'
            )
            ->addOption(
                self::OPT_IGNORE,
                'i',
                InputOption::VALUE_REQUIRED,
                'Directories to ignore',
                ''
            )
            ->addOption(
                self::OPT_VISIBILITY,
                null,
                InputOption::VALUE_OPTIONAL,
                'The visibility of the methods to import, a comma-separated list.',
                ''
            )
            ->addOption(
                self::OPT_METHOD_REGEX,
                null,
                InputOption::VALUE_OPTIONAL,
                'The full regular expression methods should match to be included in the output.',
                ''
            )
            ->addOption(
                self::OPT_TABLE_GENERATOR,
                null,
                InputOption::VALUE_OPTIONAL,
                'The slug of a supported table generator class or a fully qualified TableGenerator interface implementation class name.', // phpcs:ignore
                'default'
            )
            ->addOption(
                self::OPT_SEE,
                null,
                InputOption::VALUE_NONE,
                'Include @see in generated markdown'
            )
            ->addOption(
                self::OPT_NO_INTERNAL,
                null,
                InputOption::VALUE_NONE,
                'Ignore entities marked @internal'
            )
            ->addOption(
                self::OPT_NO_EXAMPLES,
                null,
                InputOption::VALUE_NONE,
                'Do not append @example blocks after each function table'
            );
    }

    /**
     * @throws \InvalidArgumentException|\ReflectionException
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->errorOutput = $output instanceof ConsoleOutputInterface
            ? $output->getErrorOutput()
            : $output;

        $classes = $input->getArgument(self::ARG_CLASS);
        $bootstrap = $input->getOption(self::OPT_BOOTSTRAP);
        $ignore = explode(',', $input->getOption(self::OPT_IGNORE));
        $this->visibilityFilter = empty($input->getOption(self::OPT_VISIBILITY))
            ? ['public', 'protected', 'abstract', 'final']
            : array_map(
                'trim',
                preg_split('/\\s*,\\s*/', $input->getOption(self::OPT_VISIBILITY))
            );
        $this->methodRegex = (string)$input->getOption(self::OPT_METHOD_REGEX);
        $includeSee = $input->getOption(self::OPT_SEE);
        $noInternal = $input->getOption(self::OPT_NO_INTERNAL);
        $appendExamples = !$input->getOption(self::OPT_NO_EXAMPLES);
        $requestingOneClass = false;

        if ($bootstrap) {
            // A "./" prefix resolves against the working directory and skips the
            // include_path, which is what prefixing getcwd() did — but getcwd()
            // returns false once that directory is deleted, and false
            // concatenates to "", resolving the file against the filesystem root.
            require_once str_starts_with($bootstrap, '/') ? $bootstrap : './' . $bootstrap;
        }

        $classCollection = [];
        // Listed names that cannot be documented. They were asked for, so they
        // keep their place in the table of contents — as text, since there is
        // no section to link to.
        $unlisted = [];
        if (str_contains($classes, ',')) {
            foreach (explode(',', $classes) as $class) {
                $class = trim($class);
                if ($class === '') {
                    continue;
                }
                // Written the way a directory scan and ClassEntity write class
                // names, so a name listed as text reads like the linked ones.
                $class = Utils::sanitizeClassName($class);
                if (!$this->isDocumentable($class, reportMissing: true)) {
                    $unlisted[$class] = true;
                }
                $classCollection[0][] = $class;
            }
        } elseif ($this->isDocumentable($classes)) {
            $classCollection[] = [$classes];
            $requestingOneClass = true;
        } elseif (is_dir($classes)) {
            $classCollection = $this->findClassesInDir($classes, [], $ignore);
        } else {
            throw new InvalidArgumentException('Given input is neither a class nor a source directory');
        }

        // VALUE_OPTIONAL yields null, not the declared default, when the flag is
        // written with no value and no token follows it.
        $tableGeneratorSlug = $input->getOption(self::OPT_TABLE_GENERATOR) ?? 'default';
        $tableGenerator = $this->buildTableGenerator($tableGeneratorSlug);

        $tableOfContent = [];
        $body = [];
        // Section anchors of the documented classes, by lower-cased name: PHP
        // class names are case-insensitive, and a docblock may not match the
        // declaration's case.
        $anchors = [];
        $documented = [];

        foreach ($classCollection as $classes) {
            foreach ($classes as $className) {
                if (isset($unlisted[$className])) {
                    $tableOfContent[] = '- ' . $className;
                    continue;
                }

                $class = $this->getClassEntity($className);

                if ($class->hasIgnoreTag() || ($class->hasInternalTag() && $noInternal)) {
                    continue;
                }

                // Reflection resolves a class_alias to the original, so two files
                // can yield the same entity — a deprecation shim beside the class
                // it renames. Emitting it twice duplicates the anchor id, and an
                // id has to be unique for the cross-references to resolve.
                if (isset($documented[$class->getName()])) {
                    continue;
                }
                $documented[$class->getName()] = true;

                // Add to tbl of contents
                $tableOfContent[] = sprintf(
                    '- [%s](#%s)',
                    $class->generateTitle('%name% %extra%'),
                    $class->generateAnchor()
                );

                // A single class is written without a section anchor, and a link
                // to one would lead nowhere.
                if (!$requestingOneClass) {
                    $anchors[strtolower($class->getName())] = '#' . $class->generateAnchor();
                }

                // generate function table
                // openTable() resets declareAbstraction, so both toggles are set
                // after it rather than before.
                $tableGenerator->openTable();
                $tableGenerator->appendExamplesToEndOfTable($appendExamples);
                $tableGenerator->doDeclareAbstraction(!$class->isInterface());
                $this->reportProblems($class->getName(), $class->getProblems());
                foreach ($class->getFunctions() as $func) {
                    if ($noInternal && $func->isInternal()) {
                        continue;
                    }
                    // Named by the class whose docblock it is: an inherited
                    // method's problems are in its parent's docblock.
                    $this->reportProblems($func->getClass() . '::' . $func->getName() . '()', $func->getProblems());
                    $tableGenerator->addFunc($func, $includeSee);
                }

                $docs = (
                    $requestingOneClass
                    ? ''
                    : '<hr /><a id="' . $class->generateAnchor() . '"></a>' . PHP_EOL . PHP_EOL
                );

                if ($class->isDeprecated()) {
                    // rtrim: a bare "@deprecated" carries no message, and the
                    // separator would otherwise leave a dangling trailing space.
                    $docs .= '### <del>' . $class->generateTitle() . '</del>' . PHP_EOL . PHP_EOL .
                        rtrim('> **DEPRECATED** ' . $class->getDeprecationMessage()) . PHP_EOL . PHP_EOL;
                } else {
                    $docs .= '### ' . $class->generateTitle() . PHP_EOL . PHP_EOL;
                }
                // The notice is added to the description, not put in its place.
                if ($class->getDescription()) {
                    $docs .= '> ' . $class->getDescription() . PHP_EOL . PHP_EOL;
                }

                if ($includeSee && $seeArray = $class->getSee()) {
                    foreach ($seeArray as $see) {
                        $docs .= 'See ' . $see . '<br />' . PHP_EOL;
                    }
                    $docs .= PHP_EOL;
                }

                if ($example = $class->getExample()) {
                    $line = sprintf(
                        '###### Example%s%s',
                        PHP_EOL,
                        // The selected generator, not the built-in one — a custom
                        // generator implements this as part of the interface and
                        // used to be bypassed here for class-level examples only.
                        $tableGenerator::formatExampleComment($example)
                    );

                    $docs .= $line .
                        PHP_EOL .
                        PHP_EOL;
                }

                $docs .= $tableGenerator->getTable() . PHP_EOL . PHP_EOL;

                // Written as plain names: linkClassNames() links them once every
                // section is known, and only to sections the document has.
                if ($class->getExtends()) {
                    $docs .= PHP_EOL . '*This class extends ' . $class->getExtends() . '*' . PHP_EOL;
                }

                if ($interfaces = $class->getInterfaces()) {
                    $docs .= PHP_EOL . '*This class implements ' . implode(', ', $interfaces) . '*' . PHP_EOL;
                }

                $body[] = $docs;
            }
        }

        // Listing names is not documenting them: the table of contents may hold
        // names alone, and with no class documented there is no document.
        if ($body === []) {
            throw new InvalidArgumentException('No classes found');
        }

        // Everything below is document content, not console chrome. Written
        // formatted, Symfony's OutputFormatter would consume any <info>,
        // <comment> or <error> that appears in a docblock.
        if (!$requestingOneClass) {
            $output->writeln('## Table of contents' . PHP_EOL, OutputInterface::OUTPUT_RAW);
            $output->writeln(implode(PHP_EOL, $tableOfContent), OutputInterface::OUTPUT_RAW);
        }

        $output->writeln(
            PHP_EOL . self::linkClassNames(implode(PHP_EOL, $body), $anchors),
            OutputInterface::OUTPUT_RAW
        );

        return self::SUCCESS;
    }

    /**
     * Turn every class name in the document's type cells and inheritance lines
     * into a link: to its section when the document has one, to the PHP manual
     * when PHP ships the class. A name links wherever it sits in a type — a
     * union member, a generic's parameter, a shape's value, a callable's
     * signature.
     *
     * A type cell is an <em> the table generator writes after "(", ", " or
     * " : ". The other <em>s — a description, a deprecation notice, the @see
     * line — follow a line break; they are the author's prose, where a link
     * inside a code span would show as the link syntax itself. An inheritance
     * line is the "*This class extends/implements ...*" line under a table.
     *
     * Linking runs after every section is written, so a parent documented
     * later in the run still links, and a parent left out — by @ignore or
     * --no-internal — is not linked to a section that does not exist.
     *
     * @param array<string, string> $anchors section anchors by lower-cased class name
     */
    private static function linkClassNames(string $document, array $anchors): string
    {
        // On a PCRE error — a backtrack limit on a large document is the
        // realistic one — preg_replace_callback() returns null; the document
        // is then printed without links rather than not at all.
        return preg_replace_callback(
            ['/((?<!<br \/>)<em>)(.*?)(<\/em>)/', '/^(\*This class (?:extends|implements) )(.*)(\*)$/m'],
            static fn (array $span): string => $span[1] . self::linkNames($span[2], $anchors) . $span[3],
            $document
        ) ?? $document;
    }

    /**
     * Link each class name in a piece of rendered type text.
     *
     * Rendered types write every class name in full, so a name is a backslash
     * and the identifier segments after it, taken whole: \Acme\Bar cannot match
     * the start of \Acme\BarBaz, nor the end of \Other\Acme\Bar. A string
     * literal escapes its backslashes, so a class name inside one never reads
     * as a name.
     *
     * @param array<string, string> $anchors section anchors by lower-cased class name
     */
    private static function linkNames(string $text, array $anchors): string
    {
        return preg_replace_callback(
            '/(?<![\w\\\\])(?:\\\\[A-Za-z_\x80-\xff][\w\x80-\xff]*)+/',
            static function (array $name) use ($anchors): string {
                $url = $anchors[strtolower($name[0])] ?? self::manualPage($name[0]);

                return $url === null ? $name[0] : sprintf('[%s](%s)', $name[0], $url);
            },
            $text
        ) ?? $text;
    }

    /**
     * The PHP manual page of a class, interface or enum PHP ships, or null
     * for anything else. The page name is the lower-cased name with each
     * namespace separator a hyphen — Random\Randomizer is
     * "class.random-randomizer" — and enums are filed as "enum.", not "class.".
     */
    private static function manualPage(string $className): ?string
    {
        if (!class_exists($className, false) && !interface_exists($className, false)) {
            return null;
        }

        $class = new ReflectionClass($className);
        if (!$class->isInternal()) {
            return null;
        }

        return sprintf(
            'https://php.net/manual/en/%s.%s.php',
            $class->isEnum() ? 'enum' : 'class',
            strtolower(str_replace('\\', '-', $class->getName()))
        );
    }

    /**
     * The documentable classes under a directory, grouped by namespace and
     * sorted at both levels so the document does not depend on filesystem order.
     *
     * @param array<string, list<string>> $collection what the recursion found so far
     * @param list<string> $ignores directory names to skip, at any depth
     *
     * @return array<string, list<string>> fully qualified class names by namespace
     */
    private function findClassesInDir(string $dir, array $collection = [], array $ignores = []): array
    {
        $entries = [];
        foreach (new FilesystemIterator($dir) as $f) {
            /** @var \SplFileInfo $f */
            $entries[$f->getPathname()] = $f;
        }
        ksort($entries, SORT_STRING);

        foreach ($entries as $f) {
            // A link is not followed — that keeps the scan inside the tree and
            // out of cycles — but it is reported, or a linked package would go
            // undocumented without a word.
            if ($f->isLink()) {
                $this->warnSkipping($f->getPathname(), 'symbolic link, not followed');
                continue;
            }

            // Without the extension test every file in the tree — images,
            // archives, fixtures — is read whole into memory before being
            // discarded, so one large asset can exhaust memory_limit.
            if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
                $found = $this->findClassInFile($f->getRealPath());
                if ($found !== null && $this->isDocumentable($found[1])) {
                    $collection[$found[0]][] = $found[1];
                }
            } elseif ($f->isDir() && !$this->shouldIgnoreDirectory($f->getFilename(), $ignores)) {
                $collection = $this->findClassesInDir($f->getRealPath(), $collection, $ignores);
            }
        }

        // Sort both levels: FilesystemIterator yields entries in filesystem order,
        // which would otherwise make the generated document machine-dependent.
        foreach ($collection as $ns => $classNames) {
            sort($classNames, SORT_STRING);
            $collection[$ns] = $classNames;
        }
        ksort($collection);

        return $collection;
    }

    /**
     * Whether the type can be loaded and reflected.
     *
     * class_exists() with autoloading *executes* the file. A class whose parent
     * or interface is not installed — an optional integration the consumer chose
     * not to require — throws from inside the autoloader, and that used to unwind
     * past every remaining class in the tree and produce an empty document. One
     * unloadable class must cost one class, not the whole run.
     */
    private function isDocumentable(string $className, bool $reportMissing = false): bool
    {
        try {
            $exists = class_exists($className, true) ||
                      interface_exists($className) ||
                      trait_exists($className);
        } catch (\Throwable $e) {
            $this->warnSkipping($className, $e->getMessage());

            return false;
        }

        // A name the user typed deserves a reason; a directory scan only ever
        // asks about names it read from a declaration.
        if (!$exists && $reportMissing) {
            $this->warnSkipping($className, 'no such class, interface or trait');
        }

        return $exists;
    }

    /**
     * Report on stderr each docblock tag that could not be read, naming where
     * it is. The documentation such a tag carried is missing from the output,
     * and this message is the only sign of it.
     *
     * @param list<string> $problems
     */
    private function reportProblems(string $where, array $problems): void
    {
        foreach ($problems as $problem) {
            $this->errorOutput?->writeln(sprintf('phpdoc-md: %s: %s', $where, $problem), OutputInterface::OUTPUT_RAW);
        }
    }

    /**
     * Report on stderr something the document leaves out — a class, a file —
     * and why, so an omission is never silent.
     */
    private function warnSkipping(string $subject, string $reason): void
    {
        // OUTPUT_RAW for the same reason as the document writes below: an
        // exception message may contain angle brackets that Symfony's
        // formatter would otherwise consume.
        $this->errorOutput?->writeln(
            sprintf('phpdoc-md: skipping %s (%s)', $subject, $reason),
            OutputInterface::OUTPUT_RAW
        );
    }

    /**
     * Find the namespace and the type declared in a file.
     *
     * Tokenising instead of matching substrings line by line is what makes traits
     * and enums discoverable, makes the result independent of the file's line
     * endings, and stops a stray "class" inside a string or an identifier from
     * being read as a declaration.
     *
     * @return array{0: string, 1: string}|null namespace and fully qualified
     *                                         name, or null if the file
     *                                         declares no named type
     */
    private function findClassInFile(string $file): ?array
    {
        // The @ keeps PHP from printing a read failure to stdout, which is the
        // document; the skip is reported on stderr instead.
        $source = @file_get_contents($file);
        if ($source === false) {
            $this->warnSkipping($file, 'cannot read the file');

            return null;
        }

        $ns = '';
        $class = '';
        $tokens = token_get_all($source);

        foreach ($tokens as $i => $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $name = $this->readNameAfter($tokens, $i);
                if ($name !== '') {
                    $ns = Utils::sanitizeClassName($name);
                }
            } elseif (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                // T_CLASS also fires for "Foo::class" and for anonymous classes;
                // neither is followed by a name, so both yield ''.
                $class = $this->readNameAfter($tokens, $i);
                if ($class !== '') {
                    break;
                }
            }
        }

        return $class !== '' ? [$ns, $ns . '\\' . $class] : null;
    }

    /**
     * The first name-like token after the given position, or '' if the next
     * meaningful token is not a name.
     */
    private function readNameAfter(array $tokens, int $index): string
    {
        for ($i = $index + 1, $len = count($tokens); $i < $len; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                return '';
            }
            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED], true)) {
                return $token[1];
            }

            return '';
        }

        return '';
    }

    private function shouldIgnoreDirectory(string $dirName, array $ignores): bool
    {
        foreach ($ignores as $dir) {
            $dir = trim($dir);
            // Matched by name, not by suffix: "--ignore=me" must not take "skipme".
            if ($dir !== '' && $dirName === $dir) {
                return true;
            }
        }

        return false;
    }

    protected function buildTableGenerator(string $tableGeneratorSlug = 'default'): TableGenerator
    {
        if (class_exists($tableGeneratorSlug)) {
            if (!in_array(TableGenerator::class, class_implements($tableGeneratorSlug), true)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'The table generator class should implement the %s interface.',
                        TableGenerator::class
                    )
                );
            }

            return new $tableGeneratorSlug();
        }

        $map = [
            'default' => MDTableGenerator::class,
        ];

        // Falling back to the default here meant a misspelled class name, or one
        // that is simply not autoloadable from the working directory, produced a
        // document in the wrong format and exited 0. --visibility rejects its
        // unknown values; this is the same contract.
        if (!isset($map[$tableGeneratorSlug])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unknown table generator "%s". Use a supported slug (%s) or the fully '
                    . 'qualified name of a class implementing %s.',
                    $tableGeneratorSlug,
                    implode(', ', array_keys($map)),
                    TableGenerator::class
                )
            );
        }

        $class = $map[$tableGeneratorSlug];

        return new $class();
    }

    private function getClassEntity(string $name): ClassEntity
    {
        if (!isset($this->memory[$name])) {
            $reflector = new Reflector($name);
            if (!empty($this->visibilityFilter)) {
                $reflector->setVisibilityFilter($this->visibilityFilter);
            }
            if (!empty($this->methodRegex)) {
                $reflector->setMethodRegex($this->methodRegex);
            }
            $this->memory[$name] = $reflector->getClassEntity();
        }

        return $this->memory[$name];
    }
}
