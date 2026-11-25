<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use PHPDocsMD\Entities\FunctionEntity;
use PHPDocsMD\TableGenerator;

/**
 * A third-party table generator that follows TableGenerator's markup
 * convention with markup of its own: a type in an <em> after " : ", prose in
 * an <em> after "<br />". It shows what the command links and what it leaves
 * alone for any generator, not only MDTableGenerator.
 */
class MarkupTableGenerator implements TableGenerator
{
    private string $table = '';

    public static function formatExampleComment(string $example): string
    {
        return $example;
    }

    public function appendExamplesToEndOfTable(bool $toggle): void
    {
    }

    public function openTable(): void
    {
        $this->table = '';
    }

    public function doDeclareAbstraction(bool $toggle): void
    {
    }

    public function addFunc(FunctionEntity $func, bool $includeSee = false): string
    {
        $row = sprintf(
            '- %s : <em>%s</em><br /><em>%s</em>',
            $func->getName(),
            $func->getReturnType(),
            $func->getDescription()
        );
        $this->table .= $row . PHP_EOL;

        return $row;
    }

    public function getTable(): string
    {
        return trim($this->table);
    }
}
