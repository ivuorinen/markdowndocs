<?php

declare(strict_types=1);

namespace PHPDocsMD;

/**
 * Class containing information about a function/class that's being made
 * available via a comment block
 *
 * @psalm-type ParamInfo = array{description?: string, name?: string, type?: string, default?: false}
 * @psalm-type Tags = array{
 *     return?: string,
 *     params?: array<string, ParamInfo>,
 *     description?: string,
 *     example?: string,
 *     deprecated?: string,
 *     see?: list<string>,
 *     problems?: list<string>,
 *     inheritDoc?: true,
 *     ignore?: true,
 *     internal?: true
 * }
 *
 * @package PHPDocsMD
 */
final class DocInfo
{
    /**
     * What an unwritten tag reads as. 'example' and 'deprecated' hold strings,
     * not false: the accessors below declare string returns, and false only
     * reached them by silent coercion. A constant, not the public static it
     * was: any caller could rewrite that for every DocInfo in the process.
     */
    private const array DEFAULTS = [
        'return'      => '',
        'params'      => [],
        'description' => '',
        'example'     => '',
        'deprecated'  => '',
        'see'         => [],
        'problems'    => [],
    ];

    /**
     * @var array{
     *     return: string,
     *     params: array<string, ParamInfo>,
     *     description: string,
     *     example: string,
     *     deprecated: string,
     *     see: list<string>,
     *     problems: list<string>,
     *     inheritDoc?: true,
     *     ignore?: true,
     *     internal?: true
     * }
     */
    private array $data;
    private bool $hasDeprecatedTag;

    /**
     * @param Tags $data the tags actually written, as DocInfoExtractor reads them
     */
    public function __construct(array $data = [])
    {
        // $data holds only the tags actually written; DEFAULTS supplies the
        // rest. So "was @deprecated present?" has to be answered before the
        // merge — a bare @deprecated carries no message, and testing the message
        // treated it as absent.
        $this->hasDeprecatedTag = array_key_exists('deprecated', $data);
        $this->data = array_merge(self::DEFAULTS, $data);
    }

    public function isDeprecated(): bool
    {
        return $this->hasDeprecatedTag;
    }

    public function getReturnType(): string
    {
        return $this->data['return'];
    }

    /**
     * What the docblock says about one parameter, looked up as "$name"; an
     * empty array when it says nothing. 'type' is absent for a typeless
     * "@param $name ..." and for one whose type could not be read.
     *
     * @return ParamInfo
     */
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

    /**
     * @return list<string> one rendered reference per @see tag that names one
     */
    public function getSee(): array
    {
        return $this->data['see'];
    }

    /**
     * The tags that could not be read, each as "cannot read "<tag>": <reason>".
     *
     * @return list<string>
     */
    public function getProblems(): array
    {
        return $this->data['problems'];
    }

    public function shouldInheritDoc(): bool
    {
        // DocInfoExtractor files every spelling of the tag under this one key.
        return isset($this->data['inheritDoc']);
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
