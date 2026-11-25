<?php

declare(strict_types=1);

namespace PHPDocsMD\Tests;

use PHPDocsMD\Entities\FunctionEntity;
use PHPDocsMD\Reflections\Reflector;
use PHPUnit\Framework\TestCase;

/**
 * The Reflector tests are split by subject; this is the lookup they share.
 */
abstract class ReflectorTestCase extends TestCase
{
    /**
     * One documented method of a fixture class.
     *
     * @throws \ReflectionException
     */
    protected function functionOf(string $className, string $method): FunctionEntity
    {
        foreach ((new Reflector($className))->getClassEntity()->getFunctions() as $function) {
            if ($function->getName() === $method) {
                return $function;
            }
        }

        $this->fail(sprintf('%s has no documented method %s()', $className, $method));
    }
}
