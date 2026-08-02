<?php

declare(strict_types=1);

namespace PHPDocsMD;

use ReflectionClass;

use function array_unique;
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
        // Pseudo-types phpDocumentor, psalm and PHPStan understand. Every name
        // here is either a reserved word or contains a hyphen, so none of them
        // can be a real class — without them "list<Clause>" was prefixed with
        // the current namespace and rendered as the class "\Ns\list<Clause>".
        'list',
        'empty',
        'array-key',
        'class-string',
        'non-empty-list',
        'non-empty-array',
        'non-empty-string',
        'numeric-string',
        'positive-int',
        'negative-int',
        'non-positive-int',
        'non-negative-int',
        'literal-int',
        'int-mask',
        'int-mask-of',
        'int-range',
        'literal-string',
        'non-falsy-string',
        'lowercase-string',
        'non-empty-lowercase-string',
        'interface-string',
        'trait-string',
        'enum-string',
        'callable-string',
        'callable-array',
        'associative-array',
        'class-string-map',
        'properties-of',
        'key-of',
        'value-of',
        // Deliberately absent: "scalar", "numeric", "arraykey" and friends. They
        // are pseudo-types too, but they carry no hyphen and are not reserved, so
        // a real class may be called Scalar or Numeric — listing them would make
        // that class un-referenceable. The hyphen is what makes the rest safe.
    ];

    public static function isNativeType(string $type = ''): bool
    {
        $type = strtolower(trim($type));
        $type = trim($type, '\\');

        return in_array($type, self::$nativeTypes, true);
    }

    public static function getClassBaseName(string $fullClassName): string
    {
        $parts = explode('\\', trim($fullClassName));

        return end($parts);
    }

    public static function sanitizeDeclaration(
        string $typeDeclaration,
        string $currentNameSpace,
        string $delimiter = '|'
    ): string {
        $parts = explode($delimiter, $typeDeclaration);
        foreach ($parts as $key => $p) {
            $p = trim($p);
            // An intersection is not one class name. Without this the whole of
            // "A&B" passes isClassReference() — it contains no space — and comes
            // back as the non-existent class "\Ns\A&B".
            if ($delimiter === '|' && str_contains($p, '&')) {
                $parts[$key] = self::sanitizeDeclaration($p, $currentNameSpace, '&');
                continue;
            }
            // A generic — "list<Clause>", "array<string>", "Collection<Foo>" — is
            // a base type plus a parameter list. Decide on the base name alone:
            // the whole string used to be treated as one class name, producing
            // "\Ns\list<Clause>", and the "<...>" also hid the native "array"
            // from isNativeType().
            $generic = '';
            if (preg_match('/^([^<]+)(<.*>)$/', $p, $g) === 1) {
                $p = $g[1];
                $generic = $g[2];
            }

            if (self::shouldPrefixWithNamespace($p)) {
                $p = self::sanitizeClassName('\\' . trim($currentNameSpace, '\\') . '\\' . $p);
            } elseif (self::isClassReference($p)) {
                $p = self::sanitizeClassName($p);
            }
            $parts[$key] = $p . $generic;
        }

        $parts = array_unique($parts, SORT_STRING);

        return implode($delimiter === '|' ? ' | ' : ' & ', $parts);
    }

    private static function shouldPrefixWithNameSpace(string $typeDeclaration): bool
    {
        return !str_starts_with($typeDeclaration, '\\') && self::isClassReference($typeDeclaration);
    }

    public static function isClassReference(string $typeDeclaration): bool
    {
        return !in_array(self::getSanitizedTypeDeclaration($typeDeclaration), self::$nativeTypes, true) &&
               !str_contains($typeDeclaration, ' ');
    }

    public static function sanitizeClassName(string $name): string
    {
        return '\\' . trim($name, ' \\');
    }

    /**
     * @throws \ReflectionException
     */
    public static function isNativeClassReference(string $typeDeclaration): bool
    {
        $sanitizedType = str_replace('[]', '', $typeDeclaration);
        if (class_exists($sanitizedType, false) && self::isClassReference($typeDeclaration)) {
            $reflectionClass = new ReflectionClass($sanitizedType);

            return !$reflectionClass->getFileName();
        }

        return false;
    }

    /**
     * Normalise a declaration for lookup in self::$nativeTypes. This must strip
     * the leading backslash exactly as isNativeType() does, or the two disagree
     * and "\string" comes out both native and a class reference.
     */
    private static function getSanitizedTypeDeclaration(string $typeDeclaration): string
    {
        return trim(strtolower(rtrim(trim($typeDeclaration), '[]')), '\\');
    }
}
