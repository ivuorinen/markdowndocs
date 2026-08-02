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

        // Application::add() was removed in Symfony 8; addCommand() replaced it in 7.4.
        if (method_exists($this, 'addCommand')) {
            $this->addCommand($command);
        } else {
            $this->add($command);
        }

        return parent::run($input, $output);
    }
}
