<?php

declare(strict_types=1);

namespace PHPDocsMD;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFloatNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\NodeTraverser;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use PHPStan\PhpDocParser\Printer\Printer;

/**
 * Reads, resolves and writes PHPDoc type expressions.
 *
 * phpstan/phpdoc-parser does the reading and writing. What it cannot know is
 * which names are classes: that depends on the namespace, the imports and the
 * names the docblocks declare themselves, which is what an instance holds.
 *
 * @package PHPDocsMD
 */
final class TypeResolver
{
    /**
     * What the parts of a hyphenated pseudo-type narrow, keyed by its last
     * segment: "non-empty-string" is a string, "callable-array" an array.
     */
    private const array PSEUDO_TYPE_BASES = [
        'string' => 'string',
        'int' => 'int',
        'array' => 'array',
        'list' => 'array',
        'callable' => 'callable',
        'object' => 'object',
        'resource' => 'resource',
        'mixed' => 'mixed',
    ];

    /**
     * Narrower names whose base the last-segment rule does not give.
     */
    private const array REFINED_NAMES = [
        'list' => 'array',
        'true' => 'bool',
        'false' => 'bool',
        'integer' => 'int',
        'boolean' => 'bool',
        'int-mask' => 'int',
        'int-mask-of' => 'int',
    ];

    private static ?Lexer $lexer = null;
    private static ?TypeParser $typeParser = null;

    private readonly string $declaringClass;

    /**
     * The file's imports keyed by lower-cased local name: PHP matches an
     * import alias without regard to case.
     *
     * @var array<string, string>
     */
    private readonly array $imports;

    /**
     * @param string $namespace the namespace relative names resolve against
     * @param array<string, string> $imports the file's use statements, local
     *                                       name => fully qualified class, as
     *                                       UseInspector reports them
     * @param string $declaringClass the class "self", "static" and "$this" name
     * @param list<string> $localNames template and type alias names, which are
     *                                 declared by docblocks and are not classes
     */
    public function __construct(
        private readonly string $namespace,
        array $imports = [],
        string $declaringClass = '',
        private readonly array $localNames = []
    ) {
        $this->imports = array_change_key_case($imports);
        $this->declaringClass = $declaringClass === '' ? '' : Utils::sanitizeClassName($declaringClass);
    }

    /**
     * Read a type expression in PHPStan/Psalm syntax. The whole text must be
     * the type: "int $x" is rejected, not read as "int".
     *
     * @throws \PHPStan\PhpDocParser\Parser\ParserException when the text is not
     *                                                     exactly one type
     */
    public static function parse(string $type): TypeNode
    {
        $tokens = new TokenIterator(self::lexer()->tokenize($type));
        $node = self::typeParser()->parse($tokens);
        $tokens->consumeTokenType(Lexer::TOKEN_END);

        return $node;
    }

    /**
     * The type with every class name in it fully qualified, wherever the name
     * sits: a generic's parameters, a shape's values, a callable's signature.
     */
    public function resolve(TypeNode $type): TypeNode
    {
        $visitor = new ClassNameQualifier($this->resolveName(...), $this->localNames, $this->declaringClass);
        $resolved = (new NodeTraverser([$visitor]))->traverse([$type])[0];
        assert($resolved instanceof TypeNode);

        return $resolved;
    }

    /**
     * Write a type the way the generated markdown writes types.
     *
     * The library's printer chooses the syntax — parentheses, separators — so
     * that what it writes reads back as the same type. It writes unions as
     * "int|string"; the separators are spaced here, so that a long union can
     * wrap in a table cell, by re-reading the printed type and spacing exactly
     * the union and intersection tokens. A "|" inside a string literal is part
     * of a different token and stays as it is.
     */
    public static function print(TypeNode $type): string
    {
        $printed = '';
        foreach (self::lexer()->tokenize((new Printer())->print($type)) as [$value, $kind]) {
            $printed .= match ($kind) {
                Lexer::TOKEN_UNION => ' | ',
                Lexer::TOKEN_INTERSECTION => ' & ',
                default => $value,
            };
        }

        return $printed;
    }

    /**
     * The members of a union, flattened; a nullable type is its type and null.
     *
     * @return list<TypeNode>
     */
    public static function members(TypeNode $type): array
    {
        if ($type instanceof UnionTypeNode) {
            $members = [];
            foreach ($type->types as $member) {
                array_push($members, ...self::members($member));
            }

            return $members;
        }

        if ($type instanceof NullableTypeNode) {
            return [...self::members($type->type), new IdentifierTypeNode('null')];
        }

        return [$type];
    }

    /**
     * One type made of the given members, each once. Members compare as
     * comparable() writes them.
     *
     * @param list<TypeNode> $members
     */
    public static function union(array $members): TypeNode
    {
        $unique = [];
        foreach ($members as $member) {
            $unique[self::comparable($member)] ??= $member;
        }
        $unique = array_values($unique);

        return count($unique) === 1 ? $unique[0] : new UnionTypeNode($unique);
    }

    /**
     * Whether $narrow states $wide, or a narrower case of it: "list<int>" is an
     * array, "class-string<Foo>" a string, "true" a bool, anything a mixed.
     * Both types are expected resolved.
     */
    public static function refines(TypeNode $narrow, TypeNode $wide): bool
    {
        $wide = self::comparable($wide);

        return $wide === 'mixed'
            || self::comparable($narrow) === $wide
            || self::base($narrow) === $wide;
    }

    /**
     * A type written for comparison. PHP compares class names and type
     * keywords without regard to case, so names are lower-cased; everything
     * else is case-sensitive and kept as printed. Lower-casing the whole
     * expression made 'GET' and 'get' one literal, and array{Id: int} and
     * array{id: int} one shape.
     */
    private static function comparable(TypeNode $type): string
    {
        $tokens = self::lexer()->tokenize(self::print($type));
        $comparable = '';
        foreach ($tokens as $index => [$value, $kind]) {
            $comparable .= $kind === Lexer::TOKEN_IDENTIFIER && !self::isCaseSensitiveName($tokens, $index)
                ? strtolower($value)
                : $value;
        }

        return $comparable;
    }

    /**
     * Whether the name token is a shape key — followed by ":" or "?:" in the
     * printed type — or a class constant's name, after "::". Both are
     * case-sensitive in PHP.
     *
     * @param list<array{string, int, int}> $tokens
     */
    private static function isCaseSensitiveName(array $tokens, int $index): bool
    {
        $next = $tokens[$index + 1][1] ?? null;

        return $next === Lexer::TOKEN_COLON
            || ($next === Lexer::TOKEN_NULLABLE && ($tokens[$index + 2][1] ?? null) === Lexer::TOKEN_COLON)
            || ($tokens[$index - 1][1] ?? null) === Lexer::TOKEN_DOUBLE_COLON;
    }

    /**
     * The type a narrower type is a case of, lower-cased.
     */
    private static function base(TypeNode $type): string
    {
        if ($type instanceof GenericTypeNode) {
            return self::base($type->type);
        }

        if ($type instanceof CallableTypeNode) {
            return self::base($type->identifier);
        }

        if ($type instanceof ArrayTypeNode || $type instanceof ArrayShapeNode) {
            return 'array';
        }

        if ($type instanceof ObjectShapeNode) {
            return 'object';
        }

        if ($type instanceof ConstTypeNode) {
            return match (true) {
                $type->constExpr instanceof ConstExprStringNode => 'string',
                $type->constExpr instanceof ConstExprIntegerNode => 'int',
                $type->constExpr instanceof ConstExprFloatNode => 'float',
                default => self::comparable($type),
            };
        }

        $name = self::comparable($type);
        if (isset(self::REFINED_NAMES[$name])) {
            return self::REFINED_NAMES[$name];
        }

        $lastSegment = substr((string)strrchr('-' . $name, '-'), 1);

        return str_contains($name, '-') ? (self::PSEUDO_TYPE_BASES[$lastSegment] ?? $name) : $name;
    }

    /**
     * Qualify a name the way PHP qualifies one in code. The import whose alias
     * is the name's first segment supplies the namespace, and only when there
     * is none is the current namespace prepended. A name written in full is
     * never looked up.
     */
    private function resolveName(string $name): string
    {
        if ($this->declaringClass !== '' && in_array(strtolower($name), ['self', 'static'], true)) {
            return $this->declaringClass;
        }

        if (!Utils::isClassReference($name) || str_starts_with($name, '\\')) {
            return $name;
        }

        // "namespace\Foo" names Foo in the current namespace; the keyword is
        // neither an import alias nor a namespace segment.
        if (strncasecmp($name, 'namespace\\', 10) === 0) {
            return Utils::sanitizeClassName($this->namespace . substr($name, 9));
        }

        $firstSegment = explode('\\', $name, 2)[0];
        $import = $this->imports[strtolower($firstSegment)] ?? null;

        return $import !== null
            ? $import . substr($name, strlen($firstSegment))
            : Utils::sanitizeClassName($this->namespace . '\\' . $name);
    }

    private static function lexer(): Lexer
    {
        return self::$lexer ??= new Lexer(new ParserConfig([]));
    }

    private static function typeParser(): TypeParser
    {
        if (self::$typeParser === null) {
            $config = new ParserConfig([]);
            self::$typeParser = new TypeParser($config, new ConstExprParser($config));
        }

        return self::$typeParser;
    }
}
