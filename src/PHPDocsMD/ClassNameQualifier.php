<?php

declare(strict_types=1);

namespace PHPDocsMD;

use PHPStan\PhpDocParser\Ast\AbstractNodeVisitor;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ThisTypeNode;

/**
 * Qualifies every class name in a parsed type, wherever the name sits: a
 * generic's parameters, a shape's values, a callable's signature. Names that
 * are not classes where they are written — shape keys, the bounds of an int
 * range, template and alias names — are left as written.
 *
 * TypeResolver::resolve() runs it over a type; it holds no state between types.
 *
 * @internal
 *
 * @package PHPDocsMD
 */
final class ClassNameQualifier extends AbstractNodeVisitor
{
    /** Marks a name that is not a class in the place it is written. */
    private const string AS_WRITTEN = 'phpdocsmd.asWritten';

    /** @var list<list<string>> template names of the callables being visited */
    private array $callableTemplates = [];

    /**
     * @param \Closure(string): string $resolveName qualifies one class name
     * @param list<string> $localNames template and alias names the docblocks declare
     * @param string $declaringClass what "$this" names, or "" to leave it as written
     */
    public function __construct(
        private readonly \Closure $resolveName,
        private readonly array $localNames,
        private readonly string $declaringClass
    ) {
    }

    #[\Override]
    public function enterNode(Node $node): ?Node
    {
        $this->markNamesWrittenAsIs($node);

        return $this->qualified($node);
    }

    #[\Override]
    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof CallableTypeNode) {
            array_pop($this->callableTemplates);
        }

        return null;
    }

    /**
     * Record the names under this node that are not classes, before the
     * traversal reaches them.
     */
    private function markNamesWrittenAsIs(Node $node): void
    {
        if ($node instanceof ArrayShapeItemNode || $node instanceof ObjectShapeItemNode) {
            // A shape key is a key: "array{id: int}" has no class "id".
            $node->keyName?->setAttribute(self::AS_WRITTEN, true);
        } elseif ($node instanceof GenericTypeNode && strtolower((string)$node->type) === 'int') {
            // "int<0, max>": the bounds are keywords of the range.
            foreach ($node->genericTypes as $bound) {
                $bound->setAttribute(self::AS_WRITTEN, true);
            }
        } elseif ($node instanceof CallableTypeNode) {
            // "callable<T>(T): T" declares T for its own signature only.
            $this->callableTemplates[] = array_values(array_map(
                static fn (TemplateTagValueNode $template): string => $template->name,
                $node->templateTypes
            ));
        }
    }

    /**
     * The node with the class it names qualified, or null to keep it as it is.
     */
    private function qualified(Node $node): ?Node
    {
        if ($node instanceof ThisTypeNode && $this->declaringClass !== '') {
            return new IdentifierTypeNode($this->declaringClass);
        }
        if ($node instanceof IdentifierTypeNode && !$this->isLocal($node)) {
            return new IdentifierTypeNode(($this->resolveName)($node->name));
        }
        if ($node instanceof ConstFetchNode && $node->className !== '') {
            // "Foo::BAR" names a class too.
            $node->className = ($this->resolveName)($node->className);
        }

        return null;
    }

    private function isLocal(IdentifierTypeNode $node): bool
    {
        return $node->getAttribute(self::AS_WRITTEN) === true
            || in_array($node->name, $this->localNames, true)
            || in_array($node->name, array_merge([], ...$this->callableTemplates), true);
    }
}
