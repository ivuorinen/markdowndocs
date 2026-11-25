<?php

declare(strict_types=1);

namespace PHPDocsMD;

use function in_array;
use function strtolower;

/**
 * Utilities.
 *
 * @package PHPDocsMD
 */
class Utils
{
    public static array $nativeTypes = [
        'mixed',
        'string',
        'int',
        'float',
        'integer',
        'number',
        'bool',
        'boolean',
        'object',
        'false',
        'true',
        'null',
        'array',
        'void',
        'callable',
        'iterable',
        'never',
        'resource',
        'self',
        'static',
        'parent',
        // Pseudo-types phpDocumentor, psalm and PHPStan understand. Both are
        // reserved words, so neither can be a real class — without them
        // "list<Clause>" was prefixed with the current namespace and rendered as
        // the class "\Ns\list<Clause>".
        'list',
        'empty',
        // Every hyphenated pseudo-type — "class-string", "non-empty-list" and
        // the dozens of others — is recognised by its hyphen instead; see
        // isNativeType().
        //
        // Deliberately absent: "scalar", "numeric", "arraykey" and friends. They
        // are pseudo-types too, but they carry no hyphen and are not reserved, so
        // a real class may be called Scalar or Numeric — listing them would make
        // that class un-referenceable. The hyphen is what makes the rest safe.
    ];

    /**
     * Whether the name is a type keyword rather than a class. No PHP identifier
     * can contain a hyphen, so every hyphenated name is a pseudo-type, including
     * the ones PHPStan and Psalm add after this list was written.
     */
    public static function isNativeType(string $type = ''): bool
    {
        $type = strtolower(trim($type));
        $type = trim($type, '\\');

        return in_array($type, self::$nativeTypes, true) || str_contains($type, '-');
    }

    public static function getClassBaseName(string $fullClassName): string
    {
        $parts = explode('\\', trim($fullClassName));

        return end($parts);
    }

    /**
     * Whether the declaration names a class. It goes through isNativeType() so
     * that the two cannot disagree: they once normalised the leading backslash
     * differently, and "\string" came out both native and a class reference.
     */
    public static function isClassReference(string $typeDeclaration): bool
    {
        return !self::isNativeType(rtrim(trim($typeDeclaration), '[]')) &&
               !str_contains($typeDeclaration, ' ');
    }

    /**
     * A class name in the one form the generator compares and prints: one
     * leading backslash, no surrounding spaces or backslashes.
     */
    public static function sanitizeClassName(string $name): string
    {
        return '\\' . trim($name, ' \\');
    }
}
