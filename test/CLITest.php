<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use InvalidArgumentException;
use PHPDocsMD\Console\CLI;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
     * Code loaded without Composer's runtime API — copied into a project by
     * hand, or bundled without it — has no installed version to report.
     */
    #[RunInSeparateProcess]
    public function testVersionIsUnknownWithoutComposersRuntimeApi(): void
    {
        $this->assertFalse(
            class_exists(InstalledVersions::class, false),
            'InstalledVersions is already loaded, so its absence cannot be simulated'
        );
        $this->detachComposerLoaders(InstalledVersions::class);

        $this->assertSame('UNKNOWN', (new CLI())->getVersion());
    }

    /**
     * A fork published under another name is installed, but not as this
     * package, so there is no version of this package to report.
     */
    #[RunInSeparateProcess]
    public function testVersionIsUnknownWhenComposerDoesNotListThePackage(): void
    {
        // With no loader registered, InstalledVersions answers from the data
        // given to reload() alone instead of re-reading each vendor directory.
        $this->detachComposerLoaders();
        InstalledVersions::reload([
            'root' => [
                'name'           => 'consumer/app',
                'pretty_version' => '1.0.0',
                'version'        => '1.0.0.0',
                'reference'      => null,
                'type'           => 'project',
                'install_path'   => __DIR__,
                'aliases'        => [],
                'dev'            => false,
            ],
            'versions' => [],
        ]);

        $this->assertSame('UNKNOWN', (new CLI())->getVersion());
    }

    /**
     * Unregister Composer's class loaders and keep autoloading working through
     * a wrapper that can withhold one class. Only safe in a separate process.
     */
    private function detachComposerLoaders(?string $withheld = null): void
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->unregister();
            spl_autoload_register(static function (string $class) use ($loader, $withheld): void {
                if ($class !== $withheld) {
                    $loader->loadClass($class);
                }
            });
        }
    }

    /**
     * run() registers the generate command through addCommands(), which every
     * supported symfony/console version provides. The CI matrix runs it against
     * 6.4 (prefer-lowest), 7.4 (locked) and 8.x (prefer-highest).
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
