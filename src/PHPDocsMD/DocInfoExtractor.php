<?php

declare(strict_types=1);

namespace PHPDocsMD;

use PHPDocsMD\Entities\CodeEntity;
use PHPStan\PhpDocParser\Ast\PhpDoc\DeprecatedTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\InvalidTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasImportTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use ReflectionClass;
use ReflectionMethod;

/**
 * Class that can extract information from a function/class comment
 *
 * phpstan/phpdoc-parser reads the comment. It knows where a tag's type ends
 * however many spaces the type contains — "array<string, int>", "callable(int):
 * void" — which splitting the line into words never could.
 *
 * @package PHPDocsMD
 */
final class DocInfoExtractor
{
    /**
     * Tags whose presence is the whole message, by the key DocInfo reads them
     * under. Tag names are compared lower-cased: "@inheritDoc" and
     * "@inheritdoc" are both in use.
     */
    private const array FLAG_TAGS = [
        '@ignore' => 'ignore',
        '@internal' => 'internal',
        '@inheritdoc' => 'inheritDoc',
    ];

    /**
     * Each list in increasing precedence: PHPStan reads a prefixed tag in
     * place of the plain one, and its own prefix in place of Psalm's.
     */
    private const array PARAM_TAGS = ['@param', '@psalm-param', '@phpstan-param'];
    private const array RETURN_TAGS = ['@return', '@psalm-return', '@phpstan-return'];

    private Lexer $lexer;
    private PhpDocParser $parser;

    /** @var array<string, list<string>> template and type alias names, by class */
    private array $classLocalNames = [];

    /**
     * Builds one phpdoc-parser instance, reused for every docblock this
     * extractor reads.
     */
    public function __construct()
    {
        $config = new ParserConfig([]);
        $constExprParser = new ConstExprParser($config);
        $this->lexer = new Lexer($config);
        $this->parser = new PhpDocParser($config, new TypeParser($config, $constExprParser), $constExprParser);
    }

    /**
     * Read a class's or method's docblock into a DocInfo. Types come back with
     * every class name fully qualified, resolved through `$imports` and the
     * declaring namespace; template and type alias names declared by the class
     * or method docblock stay as written. An undocumented reflection yields an
     * empty DocInfo.
     *
     * @param \ReflectionMethod|\ReflectionClass $reflection
     * @param array<string, string> $imports the declaring file's use statements,
     *                                       local alias => `\Fully\Qualified`,
     *                                       as UseInspector returns them
     *
     * @return DocInfo
     */
    public function extractInfo(ReflectionMethod|ReflectionClass $reflection, array $imports = []): DocInfo
    {
        // getDocComment() returns false for anything undocumented, which is most
        // of what this tool is asked to describe.
        $comment = $reflection->getDocComment();
        if ($comment === false) {
            return new DocInfo();
        }

        $doc = $this->parse($comment);
        $class = $reflection instanceof ReflectionClass ? $reflection : $reflection->getDeclaringClass();
        $resolver = new TypeResolver(
            $class->getNamespaceName(),
            $imports,
            $class->getName(),
            [...$this->localNamesOf($class), ...self::localNames($doc)]
        );

        return new DocInfo(
            self::description($doc) + self::typeTags($doc, $resolver) + self::proseTags($doc) + self::flags($doc)
        );
    }

    /**
     * The tags that carry types — the parameters and the return type — and a
     * message for each of them the parser could not read. A key is present
     * only when the docblock has such a tag.
     *
     * @return array{
     *     params?: array<string, array{description: string, name: string, type?: string, default: false}>,
     *     problems?: list<string>,
     *     return?: string
     * }
     */
    private static function typeTags(PhpDocNode $doc, TypeResolver $resolver): array
    {
        $data = [];
        $params = self::params($doc, $resolver);
        if ($params !== []) {
            $data['params'] = $params;
        }

        $problems = self::problems($doc);
        if ($problems !== []) {
            $data['problems'] = $problems;
        }

        foreach (self::RETURN_TAGS as $tagName) {
            foreach ($doc->getReturnTagValues($tagName) as $return) {
                $data['return'] = TypeResolver::print($resolver->resolve($return->type));
            }
        }

        return $data;
    }

    /**
     * The tags that carry text: the deprecation notice, the references and
     * the examples. A key is present only when the docblock has such a tag.
     *
     * @return array{deprecated?: string, see?: list<string>, example?: string}
     */
    private static function proseTags(PhpDocNode $doc): array
    {
        $data = [];
        // Presence of the tag, not the message: DocInfo tells "deprecated" from
        // "deprecated without a reason" by whether the key exists.
        $deprecations = $doc->getDeprecatedTagValues();
        if ($deprecations !== []) {
            $data['deprecated'] = trim(implode(' ', array_map(
                static fn (DeprecatedTagValueNode $deprecation): string => self::oneLine($deprecation->description),
                $deprecations
            )));
        }

        $see = array_values(array_filter(array_map(
            static fn (PhpDocTagNode $tag): ?string => self::seeReference(self::oneLine((string)$tag->value)),
            $doc->getTagsByName('@see')
        )));
        if ($see !== []) {
            $data['see'] = $see;
        }

        // Kept as written — indentation and blank lines are the sample's own;
        // MDTableGenerator::formatExampleComment() dedents and fences it. Only
        // the line breaks around each one go, so that one blank line, and not
        // two, separates the examples of a docblock with several.
        $examples = array_map(
            static fn (PhpDocTagNode $tag): string => trim((string)$tag->value, "\r\n"),
            $doc->getTagsByName('@example')
        );
        if ($examples !== []) {
            $data['example'] = implode(PHP_EOL . PHP_EOL, $examples);
        }

        return $data;
    }

    /**
     * The flag tags the docblock carries, each spelling under its one key.
     *
     * @return array{ignore?: true, internal?: true, inheritDoc?: true}
     */
    private static function flags(PhpDocNode $doc): array
    {
        $flags = [];
        foreach ($doc->getTags() as $tag) {
            $flag = self::FLAG_TAGS[strtolower($tag->name)] ?? null;
            if ($flag !== null) {
                $flags[$flag] = true;
            }
        }

        return $flags;
    }

    private function parse(string $comment): PhpDocNode
    {
        return $this->parser->parse(new TokenIterator($this->splitMultiLineStrings($this->lexer->tokenize($comment))));
    }

    /**
     * The lexer reads a double quote closed on a later line as one Doctrine
     * annotation string, with the newlines, the " * " decoration and any tags
     * in between inside it. In prose that is never a string: an inch mark —
     * 12" — took every tag up to the next quote into the description, and a
     * quoted phrase that wrapped kept the next line's " * ". Such a token is
     * lexed again from just after its opening quote, which leaves the quote a
     * character like any other.
     *
     * @param list<array{string, int, int}> $tokens
     *
     * @return list<array{string, int, int}>
     */
    private function splitMultiLineStrings(array $tokens): array
    {
        $split = [];
        foreach ($tokens as $token) {
            if ($token[1] !== Lexer::TOKEN_DOCTRINE_ANNOTATION_STRING || preg_match('/\R/', $token[0]) !== 1) {
                $split[] = $token;
                continue;
            }

            $split[] = ['"', Lexer::TOKEN_OTHER, $token[2]];
            // The rest is lexed alone, so it ends in a TOKEN_END of its own.
            $rest = array_slice($this->lexer->tokenize(substr($token[0], 1)), 0, -1);
            array_push($split, ...$this->splitMultiLineStrings($rest));
        }

        return $split;
    }

    /**
     * Template and type alias names the class's own docblock declares, which
     * its methods' docblocks may use.
     *
     * @return list<string>
     */
    private function localNamesOf(ReflectionClass $class): array
    {
        $comment = $class->getDocComment();

        return $this->classLocalNames[$class->getName()] ??= $comment === false
            ? []
            : self::localNames($this->parse($comment));
    }

    /**
     * @return list<string>
     */
    private static function localNames(PhpDocNode $doc): array
    {
        $names = [];
        foreach ($doc->getTags() as $tag) {
            $value = $tag->value;
            if ($value instanceof TemplateTagValueNode) {
                $names[] = $value->name;
            } elseif ($value instanceof TypeAliasTagValueNode) {
                $names[] = $value->alias;
            } elseif ($value instanceof TypeAliasImportTagValueNode) {
                $names[] = $value->importedAs ?? $value->importedAlias;
            }
        }

        return $names;
    }

    /**
     * The text before the first tag, on one line.
     *
     * "{@inheritDoc}" is phpDocumentor's inline tag, so the parser leaves it in
     * the text. It asks for the parent's documentation and is not prose.
     *
     * @return array{description: string, inheritDoc?: true}
     */
    private static function description(PhpDocNode $doc): array
    {
        $text = [];
        foreach ($doc->children as $child) {
            if (!$child instanceof PhpDocTextNode) {
                break;
            }
            $text[] = $child->text;
        }

        $description = (string)preg_replace(
            '/\s*\{@inheritdoc\}\s*/i',
            ' ',
            self::oneLine(implode(PHP_EOL, $text)),
            -1,
            $inherits
        );
        $data = ['description' => trim($description)];
        if ($inherits > 0) {
            $data['inheritDoc'] = true;
        }

        return $data;
    }

    /**
     * Parameter documentation keyed by "$name" — the parser reports "..." and
     * "&" apart from the name, so the key matches what Reflector looks up for
     * variadic and by-reference parameters too.
     *
     * A typeless "@param $name text" documents the parameter without claiming
     * a type. A prefixed tag replaces the type, and the description only if it
     * has one: "@phpstan-param" usually carries the precise type alone.
     *
     * @return array<string, array{description: string, name: string, type?: string, default: false}>
     */
    private static function params(PhpDocNode $doc, TypeResolver $resolver): array
    {
        $params = [];
        foreach ($doc->getTypelessParamTagValues() as $param) {
            $params[$param->parameterName] = [
                'description' => self::oneLine($param->description),
                'name' => $param->parameterName,
                'default' => false,
            ];
        }

        // A @param whose type could not be read still names its parameter, and
        // the text after the name is still its description: keep that much.
        foreach (self::unreadable($doc, self::PARAM_TAGS) as [, $value]) {
            if (preg_match('/(?:^|\s)&?(?:\.\.\.)?(\$\w+)(?:\s+(.*))?$/s', $value->value, $match) === 1) {
                $params[$match[1]] ??= [
                    'description' => self::oneLine($match[2] ?? ''),
                    'name' => $match[1],
                    'default' => false,
                ];
            }
        }

        foreach (self::PARAM_TAGS as $tagName) {
            foreach ($doc->getParamTagValues($tagName) as $param) {
                $description = self::oneLine($param->description);
                $params[$param->parameterName] = [
                    'description' => $description !== ''
                        ? $description
                        : ($params[$param->parameterName]['description'] ?? ''),
                    'name' => $param->parameterName,
                    'type' => TypeResolver::print($resolver->resolve($param->type)),
                    'default' => false,
                ];
            }
        }

        return $params;
    }

    /**
     * One message per @param or @return the parser could not read. The parser
     * keeps such a tag as an InvalidTagValueNode, which getParamTagValues() and
     * getReturnTagValues() skip, so without this a typo in a type dropped the
     * tag's documentation with nothing to say it happened.
     *
     * @return list<string>
     */
    private static function problems(PhpDocNode $doc): array
    {
        $problems = [];
        foreach (self::unreadable($doc, [...self::PARAM_TAGS, ...self::RETURN_TAGS]) as [$name, $value]) {
            $problems[] = sprintf(
                'cannot read "%s %s": %s',
                $name,
                self::oneLine($value->value),
                $value->exception->getMessage()
            );
        }

        return $problems;
    }

    /**
     * The tags with one of the given names that the parser could not read, as
     * tag name and value.
     *
     * @param list<string> $tagNames
     *
     * @return list<array{string, InvalidTagValueNode}>
     */
    private static function unreadable(PhpDocNode $doc, array $tagNames): array
    {
        $found = [];
        foreach ($doc->getTags() as $tag) {
            if ($tag->value instanceof InvalidTagValueNode && in_array($tag->name, $tagNames, true)) {
                $found[] = [$tag->name, $tag->value];
            }
        }

        return $found;
    }

    /**
     * A docblock wraps its prose to fit the source, not the reader: the line
     * breaks, and the indentation that aligns a wrapped tag description under
     * its first line, are not part of the text.
     */
    private static function oneLine(string $text): string
    {
        return (string)preg_replace('/\s*\R\s*/', ' ', trim($text));
    }

    /**
     * A URL becomes a link, titled by the text after it when there is any. A
     * bare "@see" names nothing: kept, it rendered as an empty "See" line under
     * the class and a dangling ", " on a method.
     */
    private static function seeReference(string $see): ?string
    {
        if ($see === '') {
            return null;
        }

        $words = preg_split('/\s+/', $see, 2) ?: [$see];
        if (preg_match('#^https?://#', $words[0]) !== 1) {
            return $see;
        }

        return isset($words[1])
            ? '[' . $words[1] . '](' . $words[0] . ')'
            : '<' . $words[0] . '>';
    }

    /**
     * Copy what every entity carries from its docblock — name, description,
     * example, @see references, problems and the internal and deprecated flags
     * — onto the entity. Types are applied by Reflector, which merges them
     * with the declaration.
     */
    public function applyInfoToEntity(
        ReflectionMethod|ReflectionClass $reflection,
        DocInfo $docInfo,
        CodeEntity $code
    ): void {
        $code->setName($reflection->getName());
        $code->setDescription($docInfo->getDescription());
        $code->setExample($docInfo->getExample());
        $code->setSee($docInfo->getSee());
        $code->setProblems($docInfo->getProblems());
        $code->isInternal($docInfo->isInternal());

        // Presence of the tag, not the message: "@deprecated" on its own is the
        // most common form, and testing the message read it as not deprecated.
        if ($docInfo->isDeprecated()) {
            $code->isDeprecated(true);
            $code->setDeprecationMessage($docInfo->getDeprecationMessage());
        }
    }
}
