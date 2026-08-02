<?php

declare(strict_types=1);

namespace PHPDocsMD;

use PHPDocsMD\Entities\FunctionEntity;

/**
 * Any class that can create a markdown-formatted table describing class functions
 * referred to via FunctionEntity objects should implement this interface.
 *
 * @package PHPDocsMD
 */
interface TableGenerator
{
    /**
     * Create a markdown-formatted code view out of an example comment.
     */
    public static function formatExampleComment(string $example): string;

    /**
     * All example comments found while generating the table will be
     * appended to the end of the table. Set $toggle to false to
     * prevent this behaviour
     */
    public function appendExamplesToEndOfTable(bool $toggle): void;

    /**
     * Begin generating a new markdown-formatted table
     */
    public function openTable(): void;

    /**
     * Toggle whether methods being abstract (or part of an interface)
     * should be declared as abstract in the table
     */
    public function doDeclareAbstraction(bool $toggle): void;

    /**
     * Generates a markdown formatted table row with information about given function. Then adds the
     * row to the table and returns the markdown formatted string.
     */
    public function addFunc(FunctionEntity $func, bool $includeSee = false): string;

    public function getTable(): string;
}
