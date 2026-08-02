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
     * @param \Symfony\Component\Console\Input\InputInterface|null $input
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output
     *
     * @throws \Exception
     */
    #[\Override]
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $command = new PHPDocsMDCommand();

        // Application::add() was removed in Symfony 8; addCommand() replaced it in
        // 7.4. Dispatched through a variable method name deliberately: written as
        // two literal calls, whichever one is absent from the installed version is
        // a hard reference psalm resolves and reports as UndefinedMethod. Silencing
        // that with @psalm-suppress is not an option either — findUnusedPsalmSuppress
        // is on, so the suppression itself then fails every leg where the method
        // does exist.
        $register = method_exists($this, 'addCommand') ? 'addCommand' : 'add';
        $this->$register($command);

        return parent::run($input, $output);
    }
}
