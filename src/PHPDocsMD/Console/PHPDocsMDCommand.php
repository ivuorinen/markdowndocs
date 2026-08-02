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
            require_once str_starts_with($bootstrap, '/') ? $bootstrap : getcwd() . '/' . $bootstrap;
        }

        $classCollection = [];
        if (str_contains($classes, ',')) {
            foreach (explode(',', $classes) as $class) {
                if ($this->isDocumentable($class)) {
                    $classCollection[0][] = $class;
                }
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
        $classLinks = [];
        $documented = [];

        foreach ($classCollection as $classes) {
            foreach ($classes as $className) {
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

                $classLinks[$class->getName()] = '#' . $class->generateAnchor();

                // generate function table
                // openTable() resets declareAbstraction, so both toggles are set
                // after it rather than before.
                $tableGenerator->openTable();
                $tableGenerator->appendExamplesToEndOfTable($appendExamples);
                $tableGenerator->doDeclareAbstraction(!$class->isInterface());
                foreach ($class->getFunctions() as $func) {
                    if ($noInternal && $func->isInternal()) {
                        continue;
                    }
                    if ($func->isReturningNativeClass()) {
                        $link = sprintf(
                            'https://php.net/manual/en/class.%s.php',
                            strtolower(
                                str_replace(['[]', '\\'], '', $func->getReturnType())
                            )
                        );
                        $classLinks[$func->getReturnType()] = $link;
                    }
                    foreach ($func->getParams() as $param) {
                        if ($param->getNativeClassType()) {
                            $link = sprintf(
                                'https://php.net/manual/en/class.%s.php',
                                strtolower(str_replace(['[]', '\\',], '', $param->getNativeClassType()))
                            );
                            $classLinks[$param->getNativeClassType()] = $link;
                        }
                    }
                    $tableGenerator->addFunc($func, $includeSee);
                }

                $docs = (
                    $requestingOneClass
                    ? ''
                    : '<hr /><a id="' . trim($classLinks[$class->getName()], '#') . '"></a>' .
                    PHP_EOL . PHP_EOL
                );

                if ($class->isDeprecated()) {
                    // rtrim: a bare "@deprecated" carries no message, and the
                    // separator would otherwise leave a dangling trailing space.
                    $docs .= '### <del>' . $class->generateTitle() . '</del>' . PHP_EOL . PHP_EOL .
                        rtrim('> **DEPRECATED** ' . $class->getDeprecationMessage()) . PHP_EOL . PHP_EOL;
                } else {
                    $docs .= '### ' . $class->generateTitle() . PHP_EOL . PHP_EOL;
                    if ($class->getDescription()) {
                        $docs .= '> ' . $class->getDescription() . PHP_EOL . PHP_EOL;
                    }
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

                if ($class->getExtends()) {
                    $link = $class->getExtends();
                    if ($anchor = $this->getAnchorFromClassCollection(
                        $classCollection,
                        $class->getExtends()
                    )) {
                        $link = sprintf('[%s](#%s) ', $link, $anchor);
                    }

                    $docs .= PHP_EOL . '*This class extends ' . trim($link) . '*' . PHP_EOL;
                }

                if ($interfaces = $class->getInterfaces()) {
                    $interfaceNames = [];
                    foreach ($interfaces as $interface) {
                        $anchor = $this->getAnchorFromClassCollection(
                            $classCollection,
                            $interface
                        );
                        $interfaceNames[] = $anchor
                            ? sprintf('[%s](#%s) ', $interface, $anchor)
                            : $interface;
                    }
                    $docs .= PHP_EOL .
                        sprintf(
                            '*This class implements %s*',
                            trim(implode(', ', $interfaceNames))
                        ) .
                        PHP_EOL;
                }

                $body[] = $docs;
            }
        }

        if (empty($tableOfContent)) {
            throw new InvalidArgumentException('No classes found');
        }

        // Everything below is document content, not console chrome. Written
        // formatted, Symfony's OutputFormatter would consume any <info>,
        // <comment> or <error> that appears in a docblock.
        if (!$requestingOneClass) {
            $output->writeln('## Table of contents' . PHP_EOL, OutputInterface::OUTPUT_RAW);
            $output->writeln(implode(PHP_EOL, $tableOfContent), OutputInterface::OUTPUT_RAW);
        }

        // Convert references to classes into links. Longest name first, and the
        // lookahead refuses a match that runs on into a longer identifier, so
        // \Acme\Bar cannot eat the prefix of \Acme\BarBaz.
        //
        // The accepted leading context is "<em>", a "/" (a URL path), or the
        // escaped pipe MDTableGenerator::escapeCell() writes between union
        // members. Only the first member of a union sits directly after "<em>",
        // so without that third alternative every later member stayed plain text.
        uksort($classLinks, static fn (string $a, string $b) => strlen($b) <=> strlen($a));
        $unionSeparator = preg_quote('\| ', '/');
        $docString = implode(PHP_EOL, $body);
        foreach ($classLinks as $className => $url) {
            $link = sprintf('[%s](%s) ', $className, $url);
            $docString = preg_replace(
                '/(<em>|' . $unionSeparator . '|\/)' . preg_quote($className, '/') . '(?![\w\\\\])/',
                '$1' . str_replace(['\\', '$'], ['\\\\', '\\$'], $link),
                $docString
            );
        }

        $output->writeln(PHP_EOL . $docString, OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }

    private function findClassesInDir(string $dir, array $collection = [], array $ignores = []): array
    {
        $entries = [];
        foreach (new FilesystemIterator($dir) as $f) {
            /** @var \SplFileInfo $f */
            $entries[$f->getPathname()] = $f;
        }
        ksort($entries, SORT_STRING);

        foreach ($entries as $f) {
            // Without the extension test every file in the tree — images,
            // archives, fixtures — is read whole into memory before being
            // discarded, so one large asset can exhaust memory_limit.
            if ($f->isFile() && !$f->isLink() && strtolower($f->getExtension()) === 'php') {
                [$ns, $className] = $this->findClassInFile($f->getRealPath());
                if ($className && $this->isDocumentable($className)) {
                    $collection[$ns][] = $className;
                }
            } elseif ($f->isDir() &&
                !$f->isLink() &&
                !$this->shouldIgnoreDirectory($f->getFilename(), $ignores)
            ) {
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
    private function isDocumentable(string $className): bool
    {
        try {
            return class_exists($className, true) ||
                   interface_exists($className) ||
                   trait_exists($className);
        } catch (\Throwable $e) {
            // OUTPUT_RAW for the same reason as the document writes below: an
            // exception message may contain angle brackets that Symfony's
            // formatter would otherwise consume.
            $this->errorOutput?->writeln(
                sprintf('phpdoc-md: skipping %s (%s)', $className, $e->getMessage()),
                OutputInterface::OUTPUT_RAW
            );

            return false;
        }
    }

    /**
     * Find the namespace and the type declared in a file.
     *
     * Tokenising instead of matching substrings line by line is what makes traits
     * and enums discoverable, makes the result independent of the file's line
     * endings, and stops a stray "class" inside a string or an identifier from
     * being read as a declaration.
     *
     * @return array{0: string|false, 1: string|false}
     */
    private function findClassInFile(string $file): array
    {
        $ns = '';
        $class = false;
        $tokens = token_get_all((string)file_get_contents($file));

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

        return $class ? [$ns, $ns . '\\' . $class] : [false, false];
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

    private function getAnchorFromClassCollection(array $coll, string $find): ?string
    {
        foreach ($coll as $classes) {
            foreach ($classes as $className) {
                if ($className === $find) {
                    return $this->getClassEntity($className)->generateAnchor();
                }
            }
        }

        return null;
    }
}
