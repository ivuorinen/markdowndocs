<?php

declare(strict_types=1);

namespace PHPDocsMD\Entities;

use PHPDocsMD\DocInfoExtractor;

/**
 * Class capable of creating ClassEntity objects
 *
 * @package PHPDocsMD
 */
class ClassEntityFactory
{
    private DocInfoExtractor $docInfoExtractor;

    public function __construct(DocInfoExtractor $docInfoExtractor)
    {
        $this->docInfoExtractor = $docInfoExtractor;
    }

    public function create(\ReflectionClass $reflection): ClassEntity
    {
        $class = new ClassEntity();
        $class->isInterface($reflection->isInterface());
        $class->isTrait($reflection->isTrait());
        $class->isEnum($reflection->isEnum());
        $class->isAbstract($reflection->isAbstract());
        $class->setInterfaces(array_keys($reflection->getInterfaces()));

        $docInfo = $this->docInfoExtractor->extractInfo($reflection);
        $this->docInfoExtractor->applyInfoToEntity($reflection, $docInfo, $class);
        $class->hasIgnoreTag($docInfo->shouldBeIgnored());
        $class->hasInternalTag($docInfo->isInternal());

        if ($reflection->getParentClass()) {
            $class->setExtends($reflection->getParentClass()->getName());
        }

        return $class;
    }
}
