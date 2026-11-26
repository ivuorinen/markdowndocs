<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use PHPDocsMD\Console\PHPDocsMDCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Runs the generate command the way the CLI registers it. The command tests
 * are split by subject; this is the setup they share.
 */
abstract class CommandTestCase extends TestCase
{
    protected CommandTester $tester;

    protected function setUp(): void
    {
        $application = new Application();
        $application->addCommands([new PHPDocsMDCommand()]);

        $this->tester = new CommandTester($application->find('generate'));
        SpyTableGenerator::$seenIncludeSee = [];
    }
}
