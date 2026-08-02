<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use InvalidArgumentException;
use PHPDocsMD\Console\CLI;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class CLITest extends TestCase
{
    private function buildCli(): CLI
    {
        $cli = new CLI();
        // Application::run() calls exit() otherwise, which would end the test run.
        $cli->setAutoExit(false);
        $cli->setCatchExceptions(false);

        return $cli;
    }

    public function testApplicationIsNamed(): void
    {
        $this->assertEquals('PHP Markdown Documentation Generator', $this->buildCli()->getName());
    }

    /**
     * The version comes from Composer\InstalledVersions rather than a hardcoded
     * composer.json field, so it cannot drift from the installed release.
     */
    public function testVersionIsResolvedFromTheInstalledPackage(): void
    {
        $version = $this->buildCli()->getVersion();

        $this->assertNotSame('', $version);
        $this->assertNotSame('UNKNOWN', $version, 'the package should report as installed under test');
        $this->assertMatchesRegularExpression('/\d|dev/', $version);
    }

    /**
     * run() registers the generate command through whichever Application API the
     * installed Symfony provides — add() up to 7.x, addCommand() from 7.4 on.
     * Both branches are exercised across the CI matrix: the prefer-lowest leg
     * pins symfony/console 5.4, the others run 7.x or 8.x.
     */
    public function testRunRegistersTheGenerateCommand(): void
    {
        $cli = $this->buildCli();

        $exitCode = $cli->run(new ArrayInput(['--version' => true]), new BufferedOutput());

        $this->assertSame(0, $exitCode);
        $this->assertTrue($cli->has('generate'), 'generate was not registered by run()');
    }

    public function testRunExecutesTheGenerateCommand(): void
    {
        $cli = $this->buildCli();
        $output = new BufferedOutput();

        $exitCode = $cli->run(
            new ArrayInput([
                'command' => 'generate',
                'class' => __DIR__ . '/Acme/Fixtures/Links',
            ]),
            $output
        );

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('## Table of contents', $output->fetch());
    }

    public function testUnknownInputSurfacesAsAnError(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->buildCli()->run(
            new ArrayInput(['command' => 'generate', 'class' => 'No\\Such\\Class']),
            new BufferedOutput()
        );
    }
}
