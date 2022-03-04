<?php

namespace PHPDocsMD;

use PHPDocsMD\Entities\ClassEntity;
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
     * @param string $methodName
     * @param array  $classes
     *
     * @return bool|\PHPDocsMD\Entities\FunctionEntity
     */
    public function findInClasses(string $methodName, array $classes)
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
     * @return false|\PHPDocsMD\Entities\FunctionEntity
     */
    public function find(string $methodName, string $className)
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

    private function loadClassEntity(string $className): ClassEntity
    {
        if (empty($this->cache[ $className ])) {
            $reflector                 = new Reflector($className, $this);
            $this->cache[ $className ] = $reflector->getClassEntity();
        }

        return $this->cache[ $className ];
    }
}
