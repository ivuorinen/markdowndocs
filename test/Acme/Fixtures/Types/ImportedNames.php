<?php

declare(strict_types=1);

namespace Acme\Fixtures\Types;

use Acme\Fixtures\Errors\Exception;
use Acme\Fixtures\Links;
use Acme\Fixtures\Links\Bar;
use Acme\Fixtures\Links\BarBaz as Baz;

/**
 * Docblock types written with names imported from another namespace. PHP
 * resolves such a name through the import whose alias matches its first
 * segment, and only falls back to the current namespace without one.
 */
class ImportedNames
{
    /**
     * @param Bar $bar an untyped parameter documented with an import
     */
    public function untypedParam($bar): void
    {
        unset($bar);
    }

    /**
     * @param Baz|null $baz a nullable parameter documented with an alias
     */
    public function nullableAliasParam(?Baz $baz): void
    {
        unset($baz);
    }

    /**
     * @param Bar[] $bars a list documented with an import
     */
    public function listParam(array $bars): void
    {
        unset($bars);
    }

    /**
     * The signature names the class in full; the docblock uses a short name
     * that nothing imports.
     *
     * @param BarBaz $barBaz documented with an unimported short name
     */
    public function fullyQualifiedSignature(\Acme\Fixtures\Links\BarBaz $barBaz): void
    {
        unset($barBaz);
    }

    /**
     * @return Bar|null
     */
    public function unionReturn()
    {
        return null;
    }

    /**
     * Only the namespace is imported, and BarBaz is imported under another
     * name, so nothing but the first segment can resolve this one.
     *
     * @return Links\BarBaz
     */
    public function namespaceImportReturn()
    {
        return null;
    }

    /**
     * @return Exception
     */
    public function shadowsGlobalClassReturn()
    {
        return null;
    }
}
