<?php

declare(strict_types=1);

namespace PHPDocsMD\Entities;

/**
 * Object describing a function
 *
 * @package PHPDocsMD
 */
class FunctionEntity extends CodeEntity
{
    /**
     * @var \PHPDocsMD\Entities\ParamEntity[]
     */
    private array $params = [];
    private string $returnType = 'void';
    private string $visibility = 'public';
    private bool $abstract = false;
    private bool $isStatic = false;
    private string $class = '';
    private bool $isReturningNativeClass = false;

    public function isStatic(?bool $toggle = null): bool
    {
        return $toggle === null
            ? $this->isStatic
            : ($this->isStatic = $toggle);
    }

    /**
     * @param bool $isStatic
     * @return FunctionEntity
     */
    public function setIsStatic(bool $isStatic): FunctionEntity
    {
        $this->isStatic = $isStatic;
        return $this;
    }

    public function isAbstract(?bool $toggle = null): bool
    {
        return $toggle === null
            ? $this->abstract
            : ($this->abstract = $toggle);
    }

    /**
     * @param bool $abstract
     * @return \PHPDocsMD\Entities\FunctionEntity
     */
    public function setAbstract(bool $abstract): FunctionEntity
    {
        $this->abstract = $abstract;
        return $this;
    }

    public function isReturningNativeClass(?bool $toggle = null): bool
    {
        return $toggle === null
            ? $this->isReturningNativeClass
            : ($this->isReturningNativeClass = $toggle);
    }

    public function setIsReturningNativeClass(bool $isReturningNativeClass): FunctionEntity
    {
        $this->isReturningNativeClass = $isReturningNativeClass;
        return $this;
    }

    public function hasParams(): bool
    {
        return !empty($this->params);
    }

    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @param \PHPDocsMD\Entities\ParamEntity[] $params
     */
    public function setParams(array $params): FunctionEntity
    {
        $this->params = $params;

        return $this;
    }

    public function getReturnType(): string
    {
        return $this->returnType;
    }

    public function setReturnType(string $returnType): FunctionEntity
    {
        $this->returnType = $returnType;

        return $this;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function setVisibility(string $visibility): FunctionEntity
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function setClass(string $class): FunctionEntity
    {
        $this->class = $class;

        return $this;
    }
}
