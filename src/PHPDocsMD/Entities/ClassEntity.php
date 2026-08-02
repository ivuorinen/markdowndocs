<?php

declare(strict_types=1);

namespace PHPDocsMD\Entities;

use PHPDocsMD\Utils;

/**
 * Object describing a class or an interface
 *
 * @package PHPDocsMD
 */
class ClassEntity extends CodeEntity
{
    /**
     * @var FunctionEntity[]
     */
    private array $functions = [];
    private bool $isInterface = false;
    private bool $abstract = false;
    private bool $hasIgnoreTag = false;
    private bool $hasInternalTag = false;
    private string $extends = '';
    private array $interfaces = [];
    private bool $isTrait = false;
    private bool $isEnum = false;

    public function hasIgnoreTag(?bool $toggle = null): bool
    {
        return $toggle === null
            ? $this->hasIgnoreTag
            : ($this->hasIgnoreTag = $toggle);
    }

    public function hasInternalTag(?bool $toggle = null): bool
    {
        return $toggle === null
            ? $this->hasInternalTag
            : ($this->hasInternalTag = $toggle);
    }

    public function getExtends(): string
    {
        return $this->extends;
    }

    public function setExtends(string $extends): self
    {
        $this->extends = Utils::sanitizeClassName($extends);

        return $this;
    }

    public function getInterfaces(): array
    {
        return $this->interfaces;
    }

    public function setInterfaces(array $implements): self
    {
        $this->interfaces = [];
        foreach ($implements as $interface) {
            $this->interfaces[] = Utils::sanitizeClassName($interface);
        }

        return $this;
    }

    public function getFunctions(): array
    {
        return $this->functions;
    }

    /**
     * @param FunctionEntity[] $functions
     */
    public function setFunctions(array $functions): self
    {
        $this->functions = $functions;

        return $this;
    }

    #[\Override]
    public function setName(string $name): self
    {
        parent::setName(Utils::sanitizeClassName($name));

        return $this;
    }

    /**
     * Check whether this object is referring to given class name or object instance
     */
    public function isSame(object|string $class): bool
    {
        $className = is_object($class) ? get_class($class) : $class;

        return Utils::sanitizeClassName($className) === $this->getName();
    }

    /**
     * Generates an anchor link out of the generated title (see generateTitle)
     */
    public function generateAnchor(): string
    {
        $title = $this->generateTitle();

        // The namespace separator becomes a hyphen rather than being deleted:
        // dropping it collapsed \A\FooBar and \A\Foo\Bar onto one anchor, so the
        // table of contents and every cross-reference for the second class
        // pointed at the first.
        return strtolower(
            (string)preg_replace(
                '/-+/',
                '-',
                str_replace(
                    [':', ' ', '\\', '(', ')'],
                    ['', '-', '-', '', ''],
                    $title
                )
            )
        );
    }

    /**
     * Generate a title describing the class this object is referring to
     */
    public function generateTitle(string $format = '%label%: %name% %extra%'): string
    {
        $translate = [
            // A trait cannot be instantiated and an enum has closed instances;
            // labelling either "Class" tells a reader the wrong thing about how
            // to use it. Reflection reports the kind — this used to not ask.
            '%label%' => match (true) {
                $this->isInterface() => 'Interface',
                $this->isTrait() => 'Trait',
                $this->isEnum() => 'Enum',
                default => 'Class',
            },
            '%name%' => substr_count($this->getName(), '\\') === 1
                ? substr($this->getName(), 1)
                : $this->getName(),
            '%extra%' => '',
        ];

        if (!str_contains($format, '%label%')) {
            if ($this->isInterface()) {
                $translate['%extra%'] = '(interface)';
            } elseif ($this->isAbstract()) {
                $translate['%extra%'] = '(abstract)';
            }
        } else {
            $translate['%extra%'] = $this->isAbstract() && !$this->isInterface() ? '(abstract)' : '';
        }

        return trim(strtr($format, $translate));
    }

    public function isInterface(?bool $toggle = null): bool
    {
        return $toggle === null
            ? $this->isInterface
            : ($this->isInterface = $toggle);
    }

    public function isTrait(?bool $toggle = null): bool
    {
        return $toggle === null
            ? $this->isTrait
            : ($this->isTrait = $toggle);
    }

    public function isEnum(?bool $toggle = null): bool
    {
        return $toggle === null
            ? $this->isEnum
            : ($this->isEnum = $toggle);
    }

    public function isAbstract(?bool $toggle = null): bool
    {
        return $toggle === null
            ? $this->abstract
            : ($this->abstract = $toggle);
    }
}
