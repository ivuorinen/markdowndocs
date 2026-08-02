<?php

declare(strict_types=1);

namespace PHPDocsMD;

use PHPDocsMD\Entities\ClassEntity;
use PHPDocsMD\Entities\FunctionEntity;
use PHPDocsMD\Reflections\Reflector;

/**
 * Find a specific function in a class or an array of classes
 *
 * @package PHPDocsMD
 */
class FunctionFinder
{
    private array $cache = [];

    /**
     * @return \PHPDocsMD\Entities\FunctionEntity|false
     * @throws \ReflectionException
     */
    public function findInClasses(string $methodName, array $classes): FunctionEntity|false
    {
        foreach ($classes as $className) {
            $function = $this->find($methodName, $className);
            if (false !== $function) {
                return $function;
            }
        }

        return false;
    }

    /**
     * @return \PHPDocsMD\Entities\FunctionEntity|false
     * @throws \ReflectionException
     */
    public function find(string $methodName, string $className): FunctionEntity|false
    {
        if ($className) {
            $classEntity = $this->loadClassEntity($className);
            $functions   = $classEntity->getFunctions();
            foreach ($functions as $function) {
                if ($function->getName() === $methodName) {
                    return $function;
                }
            }
            if ($classEntity->getExtends()) {
                return $this->find($methodName, $classEntity->getExtends());
            }
        }

        return false;
    }

    /**
     * @throws \ReflectionException
     */
    private function loadClassEntity(string $className): ClassEntity
    {
        if (empty($this->cache[$className])) {
            $reflector                 = new Reflector($className, $this);
            $this->cache[$className] = $reflector->getClassEntity();
        }

        return $this->cache[$className];
    }
}
