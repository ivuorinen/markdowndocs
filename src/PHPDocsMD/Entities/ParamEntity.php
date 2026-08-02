<?php

declare(strict_types=1);

namespace PHPDocsMD\Entities;

use PHPDocsMD\Utils;

/**
 * Object describing a function parameter
 *
 * @package PHPDocsMD
 */
class ParamEntity extends CodeEntity
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

    /**
     * @throws \ReflectionException
     */
    public function getNativeClassType(): ?string
    {
        foreach (preg_split('/\s*\|\s*/', $this->type) as $typeDeclaration) {
            if (Utils::isNativeClassReference($typeDeclaration)) {
                return $typeDeclaration;
            }
        }

        return null;
    }
}
