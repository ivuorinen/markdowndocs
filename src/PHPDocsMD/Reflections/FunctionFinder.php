<?php

declare(strict_types=1);

namespace PHPDocsMD\Reflections;

use PHPDocsMD\Entities\ClassEntity;
use PHPDocsMD\Entities\FunctionEntity;

/**
 * Find a specific function in a class or an array of classes
 *
 * @package PHPDocsMD
 */
final class FunctionFinder
{
    /** @var array<string, ClassEntity> */
    private array $cache = [];

    /**
     * The first declaration of the method found in the given classes or their
     * ancestors, in order; false when none declares it.
     *
     * @param list<string> $classes
     *
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
     * The method as the class, or the nearest ancestor declaring it, documents
     * it; false when nothing up the chain declares it. Used to resolve
     * {@inheritDoc}, so the entity carries the declaring class's documentation.
     *
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
