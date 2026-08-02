<?php

declare(strict_types=1);

namespace PHPDocsMD;

/**
 * Class containing information about a function/class that's being made
 * available via a comment block
 *
 * @package PHPDocsMD
 */
class DocInfo
{
    /**
     * 'example' and 'deprecated' hold strings, not false: the accessors below
     * declare string returns, and false only reached them by silent coercion.
     */
    public static array $defaultStructure = [
        'return'      => '',
        'params'      => [],
        'description' => '',
        'example'     => '',
        'deprecated'  => '',
        'see'         => [],
    ];
    private array $data;
    private bool $hasDeprecatedTag;

    public function __construct(array $data = [])
    {
        // $data holds only the tags actually written; $defaultStructure supplies
        // the rest. So "was @deprecated present?" has to be answered before the
        // merge — a bare @deprecated carries no message, and testing the message
        // treated it as absent.
        $this->hasDeprecatedTag = array_key_exists('deprecated', $data);
        $this->data = array_merge(self::$defaultStructure, $data);
    }

    public function isDeprecated(): bool
    {
        return $this->hasDeprecatedTag;
    }

    public function getReturnType(): string
    {
        return $this->data['return'];
    }

    public function getParameterInfo(string $name): array
    {
        return $this->data['params'][$name] ?? [];
    }

    public function getExample(): string
    {
        return $this->data['example'];
    }

    public function getDescription(): string
    {
        return $this->data['description'];
    }

    public function getDeprecationMessage(): string
    {
        return $this->data['deprecated'];
    }

    public function getSee(): array
    {
        return $this->data['see'];
    }

    public function shouldInheritDoc(): bool
    {
        return isset($this->data['inheritDoc']) || isset($this->data['inheritdoc']);
    }

    public function shouldBeIgnored(): bool
    {
        return isset($this->data['ignore']);
    }

    public function isInternal(): bool
    {
        return isset($this->data['internal']);
    }
}
