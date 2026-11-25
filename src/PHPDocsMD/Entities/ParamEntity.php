<?php

declare(strict_types=1);

namespace PHPDocsMD\Entities;

/**
 * Object describing a function parameter
 *
 * @package PHPDocsMD
 */
final class ParamEntity extends CodeEntity
{
    private string $default = '';
    private bool $hasDefault = false;
    private string $type = 'mixed';

    public function getDefault(): string
    {
        return $this->default;
    }

    /**
     * Whether a default value was declared at all. Distinct from getDefault()
     * being truthy: "0" and "" are perfectly good defaults.
     */
    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }

    /**
     * Records the default as it is to be printed, and that one exists:
     * hasDefault() is what tells "no default" from a default of "" or "0".
     */
    public function setDefault(string $default): self
    {
        $this->default = $default;
        $this->hasDefault = true;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }
}
