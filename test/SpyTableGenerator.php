<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use PHPDocsMD\Entities\FunctionEntity;
use PHPDocsMD\TableGenerator;

/**
 * Records the arguments the command passes to a third-party table generator.
 */
class SpyTableGenerator implements TableGenerator
{
    public static array $seenIncludeSee = [];

    public static function formatExampleComment(string $example): string
    {
        return $example;
    }

    public function appendExamplesToEndOfTable(bool $toggle): void
    {
    }

    public function openTable(): void
    {
    }

    public function doDeclareAbstraction(bool $toggle): void
    {
    }

    public function addFunc(FunctionEntity $func, bool $includeSee = false): string
    {
        self::$seenIncludeSee[] = $includeSee;

        return '';
    }

    public function getTable(): string
    {
        return '';
    }
}
