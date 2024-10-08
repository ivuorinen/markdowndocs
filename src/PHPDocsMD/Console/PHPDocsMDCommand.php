<?php

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
            );
    }

    /**
     * @throws \InvalidArgumentException|\ReflectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $classes = $input->getArgument(self::ARG_CLASS);
        $bootstrap = $input->getOption(self::OPT_BOOTSTRAP);
        $ignore = explode(',', $input->getOption(self::OPT_IGNORE));
        $this->visibilityFilter = empty($input->getOption(self::OPT_VISIBILITY))
            ? ['public', 'protected', 'abstract', 'final']
            : array_map(
                'trim',
                preg_split('/\\s*,\\s*/', $input->getOption(self::OPT_VISIBILITY))
            );
        $this->methodRegex = $input->getOption(self::OPT_METHOD_REGEX) ?: false;
        $includeSee = $input->getOption(self::OPT_SEE);
        $noInternal = $input->getOption(self::OPT_NO_INTERNAL);
        $requestingOneClass = false;

        if ($bootstrap) {
            require_once str_starts_with($bootstrap, '/') ? $bootstrap : getcwd() . '/' . $bootstrap;
        }

        $classCollection = [];
        if (str_contains($classes, ',')) {
            foreach (explode(',', $classes) as $class) {
                if (class_exists($class) || interface_exists($class) || trait_exists($class)) {
                    $classCollection[0][] = $class;
                }
            }
        } elseif (class_exists($classes) || interface_exists($classes) || trait_exists($classes)) {
            $classCollection[] = [$classes];
            $requestingOneClass = true;
        } elseif (is_dir($classes)) {
            $classCollection = $this->findClassesInDir($classes, [], $ignore);
        } else {
            throw new InvalidArgumentException('Given input is neither a class nor a source directory');
        }

        $tableGeneratorSlug = $input->getOption(self::OPT_TABLE_GENERATOR);
        $tableGenerator = $this->buildTableGenerator($tableGeneratorSlug);

        $tableOfContent = [];
        $body = [];
        $classLinks = [];

        foreach ($classCollection as $classes) {
            foreach ($classes as $className) {
                $class = $this->getClassEntity($className);

                if ($class->hasIgnoreTag() || ($class->hasInternalTag() && $noInternal)) {
                    continue;
                }

                // Add to tbl of contents
                $tableOfContent[] = sprintf(
                    '- [%s](#%s)',
                    $class->generateTitle('%name% %extra%'),
                    $class->generateAnchor()
                );

                $classLinks[$class->getName()] = '#' . $class->generateAnchor();

                // generate function table
                $tableGenerator->openTable();
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
                    $docs .= '### <del>' . $class->generateTitle() . '</del>' . PHP_EOL . PHP_EOL .
                        '> **DEPRECATED** ' . $class->getDeprecationMessage() . PHP_EOL . PHP_EOL;
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
                        MDTableGenerator::formatExampleComment($example)
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

        if (!$requestingOneClass) {
            $output->writeln('## Table of contents' . PHP_EOL);
            $output->writeln(implode(PHP_EOL, $tableOfContent));
        }

        // Convert references to classes into links
        asort($classLinks);
        $classLinks = array_reverse($classLinks, true);
        $docString = implode(PHP_EOL, $body);
        foreach ($classLinks as $className => $url) {
            $link = sprintf('[%s](%s) ', $className, $url);
            $find = ['<em>' . $className, '/' . $className];
            $replace = ['<em>' . $link, '/' . $link];
            $docString = str_replace($find, $replace, $docString);
        }

        $output->writeln(PHP_EOL . $docString);

        return 0;
    }

    private function findClassesInDir(string $dir, array $collection = [], array $ignores = []): array
    {
        foreach (new FilesystemIterator($dir) as $f) {
            /** @var \SplFileInfo $f */
            if ($f->isFile() && !$f->isLink()) {
                [$ns, $className] = $this->findClassInFile($f->getRealPath());
                if ($className &&
                    (
                        class_exists($className, true) ||
                        interface_exists($className) ||
                        trait_exists($className)
                    )
                ) {
                    $collection[$ns][] = $className;
                }
            } elseif ($f->isDir() &&
                !$f->isLink() &&
                !$this->shouldIgnoreDirectory($f->getFilename(), $ignores)
            ) {
                $collection = $this->findClassesInDir($f->getRealPath(), $collection);
            }
        }
        ksort($collection);

        return $collection;
    }

    private function findClassInFile(string $file): array
    {
        $ns = '';
        $class = false;

        foreach (explode(PHP_EOL, file_get_contents($file)) as $line) {
            if (str_contains($line, '*')) {
                continue;
            }

            if (str_contains($line, 'namespace')) {
                $ns = trim(current(array_slice(explode('namespace', $line), 1)), '; ');
                $ns = Utils::sanitizeClassName($ns);
            } elseif (str_contains($line, 'class')) {
                $class = $this->extractClassNameFromLine('class', $line);
                break;
            } elseif (str_contains($line, 'interface')) {
                $class = $this->extractClassNameFromLine('interface', $line);
                break;
            }
        }

        return $class ? [$ns, $ns . '\\' . $class] : [false, false];
    }

    public function extractClassNameFromLine(string $type, string $line): string
    {
        $class = trim(current(array_slice(explode($type, $line), 1)), '; ');

        return trim(current(explode(' ', $class)));
    }

    private function shouldIgnoreDirectory(string $dirName, array $ignores): bool
    {
        foreach ($ignores as $dir) {
            $dir = trim($dir);
            if (!empty($dir) && str_ends_with($dirName, $dir)) {
                return true;
            }
        }

        return false;
    }

    protected function buildTableGenerator(string $tableGeneratorSlug = 'default'): object
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

        $class = $map[$tableGeneratorSlug] ?? $map['default'];

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
