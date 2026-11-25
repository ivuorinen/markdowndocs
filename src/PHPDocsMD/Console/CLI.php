<?php

declare(strict_types=1);

namespace PHPDocsMD\Console;

use Composer\InstalledVersions;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command line interface used to extract markdown-formatted documentation from classes
 *
 * @package PHPDocsMD\Console
 */
class CLI extends Application
{
    private const PACKAGE = 'ivuorinen/markdowndocs';

    public function __construct()
    {
        parent::__construct('PHP Markdown Documentation Generator', self::resolveVersion());
    }

    /**
     * The installed version is authoritative — composer.json carries no hardcoded
     * one, so there is nothing that can drift away from the released tag.
     */
    private static function resolveVersion(): string
    {
        if (!class_exists(InstalledVersions::class)
            || !InstalledVersions::isInstalled(self::PACKAGE)
        ) {
            return 'UNKNOWN';
        }

        return InstalledVersions::getPrettyVersion(self::PACKAGE) ?? 'UNKNOWN';
    }

    /**
     * Register the generate command and run the application; the exit code is
     * the command's. bin/phpdoc-md calls this with no arguments, which reads
     * argv and writes to the console.
     *
     * @param \Symfony\Component\Console\Input\InputInterface|null $input
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output
     *
     * @throws \Exception
     */
    #[\Override]
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        // addCommands() is the one registration method every supported Symfony
        // version has: add() is deprecated in 7.4 and removed in 8, and
        // addCommand() does not exist before 7.4. It delegates to whichever of
        // the two the installed version provides.
        $this->addCommands([new PHPDocsMDCommand()]);

        return parent::run($input, $output);
    }
}
