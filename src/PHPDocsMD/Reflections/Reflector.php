<?php

declare(strict_types=1);

namespace PHPDocsMD\Reflections;

use ErrorException;
use InvalidArgumentException;
use PHPDocsMD\DocInfo;
use PHPDocsMD\DocInfoExtractor;
use PHPDocsMD\Entities\ClassEntity;
use PHPDocsMD\Entities\FunctionEntity;
use PHPDocsMD\Entities\ParamEntity;
use PHPDocsMD\TypeResolver;
use PHPDocsMD\UseInspector;
use PHPDocsMD\Utils;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

use function count;

/**
 * Class that can compute ClassEntity objects out of real classes
 *
 * @psalm-import-type ParamInfo from DocInfo
 *
 * @package PHPDocsMD
 */
final class Reflector
{
    private const array VISIBILITIES = [
        'public' => ReflectionMethod::IS_PUBLIC,
        'protected' => ReflectionMethod::IS_PROTECTED,
        'abstract' => ReflectionMethod::IS_ABSTRACT,
        'final' => ReflectionMethod::IS_FINAL,
    ];

    private string $className;
    private FunctionFinder $functionFinder;
    private DocInfoExtractor $docInfoExtractor;
    private ClassEntityFactory $classEntityFactory;
    private UseInspector $useInspector;
    /** @var list<string> */
    private array $visibilityFilter = [];
    private string $methodRegex = '';

    /**
     * @param string $className the class to reflect, with or without a leading backslash
     * @param FunctionFinder|null $functionFinder passed by FunctionFinder itself, so the
     *                                            reflection it starts for a parent
     *                                            shares its cache
     */
    public function __construct(string $className, ?FunctionFinder $functionFinder = null)
    {
        $this->className = $className;
        // The finder is the one seam anything uses: FunctionFinder passes itself
        // so the reflector reuses its cache while resolving {@inheritDoc}. The
        // other three collaborators had no caller in twenty-one construction
        // sites, and building them through a variable class name defeated static
        // analysis for all four.
        $this->functionFinder = $functionFinder ?? new FunctionFinder();
        $this->docInfoExtractor = new DocInfoExtractor();
        $this->useInspector = new UseInspector();
        $this->classEntityFactory = new ClassEntityFactory($this->docInfoExtractor);
    }

    /**
     * Reflect the class into an entity holding its documented methods —
     * public and protected, narrowed by setVisibilityFilter() and
     * setMethodRegex() — with each method's types merged from its declaration
     * and docblock.
     *
     * @throws \ReflectionException when the name is no class, interface or trait
     */
    public function getClassEntity(): ClassEntity
    {
        $className = $this->className;
        // ReflectionClass rejects an unknown name with this same exception. The
        // name reaches here as a plain string — ClassEntity stores parents and
        // interfaces normalised by Utils::sanitizeClassName() — so checking first
        // is what proves it names a type before it is reflected.
        if (!class_exists($className) && !interface_exists($className) && !trait_exists($className)) {
            throw new \ReflectionException(sprintf('Class "%s" does not exist', $className));
        }
        $classReflection = new ReflectionClass($className);
        $classEntity = $this->classEntityFactory->create($classReflection);

        $classEntity->setFunctions($this->getClassFunctions($classEntity, $classReflection));

        return $classEntity;
    }

    /**
     * @return list<FunctionEntity>
     * @throws \ReflectionException
     */
    private function getClassFunctions(
        ClassEntity $classEntity,
        ReflectionClass $reflectionClass
    ): array {
        $classUseStatements = $this->useInspector->getUseStatements($reflectionClass);
        $publicFunctions = [];
        $protectedFunctions = [];
        $methodReflections = [];

        if (count($this->visibilityFilter) === 0) {
            $methodReflections = $reflectionClass->getMethods();
        } else {
            foreach ($this->visibilityFilter as $filter) {
                $visibility = $this->translateVisibilityFilter($filter);
                $methodReflections[] = $reflectionClass->getMethods($visibility);
            }
            $methodReflections = array_merge(...$methodReflections);
        }

        if ($this->methodRegex !== '') {
            $methodRegex = $this->methodRegex;
            $methodReflections = array_filter(
                $methodReflections,
                fn (ReflectionMethod $reflectionMethod) => preg_match(
                    $methodRegex,
                    $reflectionMethod->name
                )
            );
        }

        foreach ($methodReflections as $methodReflection) {
            $func = $this->createFunctionEntity(
                $methodReflection,
                $classEntity,
                $classUseStatements
            );

            if ($func instanceof FunctionEntity) {
                if ($func->getVisibility() === 'public') {
                    $publicFunctions[$func->getName()] = $func;
                } else {
                    $protectedFunctions[$func->getName()] = $func;
                }
            }
        }

        ksort($publicFunctions);
        ksort($protectedFunctions);

        return array_values(array_merge($publicFunctions, $protectedFunctions));
    }

    /**
     * @return int-mask-of<ReflectionMethod::IS_*>
     */
    private function translateVisibilityFilter(string $filter): int
    {
        if (!isset(self::VISIBILITIES[$filter])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unknown visibility "%s". Supported: %s',
                    $filter,
                    implode(', ', array_keys(self::VISIBILITIES))
                )
            );
        }

        return self::VISIBILITIES[$filter];
    }

    /**
     * @param array<string, string> $useStatements the declaring file's imports
     *
     * @return \PHPDocsMD\Entities\FunctionEntity|false
     * @throws \ReflectionException
     */
    protected function createFunctionEntity(
        ReflectionMethod $method,
        ClassEntity $class,
        array $useStatements
    ): FunctionEntity|false {
        $func = new FunctionEntity();
        $docInfo = $this->docInfoExtractor->extractInfo($method, $useStatements);
        $this->docInfoExtractor->applyInfoToEntity($method, $docInfo, $func);

        if ($docInfo->shouldInheritDoc()) {
            $inherited = $this->findInheritedFunctionDeclaration($func, $class);
            if ($inherited instanceof FunctionEntity) {
                return $inherited;
            }
            // Nothing to inherit from. Describe the method itself rather than
            // aborting the whole document over one stray {@inheritDoc}.
        }

        if ($this->shouldIgnoreFunction($docInfo, $method, $class)) {
            return false;
        }

        $func
            ->setReturnType($this->getReturnType($docInfo, $method, $func))
            ->setParams($this->getParams($method, $docInfo))
            ->setIsStatic($method->isStatic())
            ->setVisibility($method->isPublic() ? 'public' : 'protected')
            ->setAbstract($method->isAbstract())
            ->setClass($class->getName());

        return $func;
    }

    /**
     * The parent or interface declaration a method inherits its docs from, or
     * false when nothing declares it.
     *
     * @return \PHPDocsMD\Entities\FunctionEntity|false
     * @throws \ReflectionException
     */
    private function findInheritedFunctionDeclaration(
        FunctionEntity $func,
        ClassEntity $class
    ): FunctionEntity|false {
        $funcName = $func->getName();
        $inheritedFuncDeclaration = $this->functionFinder->find(
            $funcName,
            $class->getExtends()
        );

        if (!$inheritedFuncDeclaration) {
            $inheritedFuncDeclaration = $this->functionFinder->findInClasses(
                $funcName,
                $class->getInterfaces()
            );
            if (!($inheritedFuncDeclaration instanceof FunctionEntity)) {
                return false;
            }
        }

        if (!$func->isAbstract() && !$class->isAbstract() && $inheritedFuncDeclaration->isAbstract()) {
            $inheritedFuncDeclaration->isAbstract(false);
        }

        return $inheritedFuncDeclaration;
    }

    protected function shouldIgnoreFunction(
        DocInfo $info,
        ReflectionMethod $method,
        ClassEntity $class
    ): bool {
        return $info->shouldBeIgnored() ||
               $method->isPrivate() ||
               !$class->isSame($method->getDeclaringClass()->getName());
    }

    /**
     * The declaration is authoritative — it is what the engine enforces — and
     * the docblock narrows it: "@return list<Foo>" refines ": array". A
     * docblock member that refines no declared member is left out, so
     * "@return void" cannot hide a declared "string". The docblock's type is
     * already resolved, against the imports, by DocInfoExtractor.
     */
    private function getReturnType(DocInfo $docInfo, ReflectionMethod $method, FunctionEntity $func): string
    {
        $documented = self::documentedType($docInfo->getReturnType());
        $declared = self::reflectionTypeNode($method->getReturnType(), $method->class);

        if ($declared === null) {
            return $documented === null
                ? $this->guessReturnTypeFromFuncName($func->getName())
                : TypeResolver::print(TypeResolver::union(TypeResolver::members($documented)));
        }

        $members = [];
        $documentedMembers = $documented === null ? [] : TypeResolver::members($documented);
        foreach (TypeResolver::members($declared) as $declaredMember) {
            $refining = array_values(array_filter(
                $documentedMembers,
                static fn (TypeNode $member): bool => TypeResolver::refines($member, $declaredMember)
            ));
            array_push($members, ...($refining === [] ? [$declaredMember] : $refining));
        }

        return TypeResolver::print(TypeResolver::union($members));
    }

    /**
     * A type DocInfoExtractor rendered, read back; null when there is none.
     */
    private static function documentedType(string $type): ?TypeNode
    {
        return $type === '' ? null : TypeResolver::parse($type);
    }

    private function guessReturnTypeFromFuncName(string $name): string
    {
        $mixed = ['get', 'load', 'fetch', 'find', 'create'];
        $bool = ['is', 'can', 'has', 'have', 'should'];
        foreach ($mixed as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return 'mixed';
            }
        }
        foreach ($bool as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return 'bool';
            }
        }

        return 'void';
    }

    /**
     * @return list<ParamEntity>
     */
    private function getParams(ReflectionMethod $method, DocInfo $docInfo): array
    {
        $params = [];
        foreach ($method->getParameters() as $param) {
            $paramName = '$' . $param->getName();
            $params[$param->getName()] = $this->createParameterEntity(
                $param,
                $docInfo->getParameterInfo($paramName)
            );
        }

        return array_values($params);
    }

    /**
     * @param ParamInfo $docs
     *
     * @todo Turn this into a class "FunctionEntityFactory"
     */
    private function createParameterEntity(ReflectionParameter $reflection, array $docs): ParamEntity
    {
        $declared = self::reflectionTypeNode(
            $reflection->getType(),
            $reflection->getDeclaringClass()?->getName() ?? ''
        );
        $members = self::mergeParameterTypes(self::documentedType($docs['type'] ?? ''), $declared);

        $param = new ParamEntity();
        $param->setDescription($docs['description'] ?? '');

        if ($reflection->isDefaultValueAvailable()) {
            // A default is a constant expression: a scalar, an array, null, or
            // an object from a `new` initializer or an enum case.
            /** @var scalar|array<array-key, mixed>|object|null $default */
            $default = $reflection->getDefaultValue();
            $param->setDefault(self::formatDefault($reflection, $default));

            // With nothing declared, the default is the other evidence of what
            // the parameter accepts: "@param Foo $x = 'none'" takes a string as
            // well. A declared type already admits its default — PHP refuses one
            // it does not — and a "mixed" default is refined by any type.
            $valueType = new IdentifierTypeNode($this->getTypeFromVal($default));
            if ($members === []) {
                $members = [$valueType];
            } elseif ($declared === null && !self::isRefinedBy($valueType, $members)) {
                array_unshift($members, $valueType);
            }
        }

        // "..." and "&" are separate reflection flags and getName() returns the
        // bare name, so without them the rendered signature is not the signature:
        // "f(...$a)" was documented as "f($a)", and "f(array &$out)" as one that
        // does not modify its argument. Reflection, not the docblock, names the
        // parameter: the docblock may spell it without the marker.
        $param->setName(match (true) {
            $reflection->isVariadic() => '...$',
            $reflection->isPassedByReference() => '&$',
            default => '$',
        } . $reflection->getName());

        // MDTableGenerator escapes the separators for its cells, so union members
        // keep the separator PHP actually uses.
        $param->setType(
            $members === [] ? 'mixed' : TypeResolver::print(TypeResolver::union($members))
        );

        return $param;
    }

    /**
     * The docblock's members, then each declared member the docblock does not
     * already state — exactly, or more precisely: "array<int, Foo>" states
     * "array", and listing both rendered "array<int, Foo> | array".
     *
     * @return list<TypeNode>
     */
    private static function mergeParameterTypes(?TypeNode $documented, ?TypeNode $declared): array
    {
        $members = $documented === null ? [] : TypeResolver::members($documented);

        foreach ($declared === null ? [] : TypeResolver::members($declared) as $declaredMember) {
            if (self::isRefinedBy($declaredMember, $members)) {
                continue;
            }

            // The same class written two ways: the declaration is resolved by
            // PHP, so it takes the place of a docblock name resolved elsewhere.
            $sameClass = self::findSameClass($declaredMember, $members);
            if ($sameClass === null) {
                $members[] = $declaredMember;
            } else {
                $members = array_map(
                    static fn (TypeNode $member): TypeNode => $member === $sameClass ? $declaredMember : $member,
                    $members
                );
            }
        }

        return $members;
    }

    /**
     * @param list<TypeNode> $members
     */
    private static function isRefinedBy(TypeNode $type, array $members): bool
    {
        foreach ($members as $member) {
            if (TypeResolver::refines($member, $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The member naming a class with the same short name as the given class,
     * if there is one.
     *
     * @param list<TypeNode> $members
     */
    private static function findSameClass(TypeNode $class, array $members): ?TypeNode
    {
        if (!$class instanceof IdentifierTypeNode || !Utils::isClassReference($class->name)) {
            return null;
        }

        foreach ($members as $member) {
            if ($member instanceof IdentifierTypeNode &&
                Utils::isClassReference($member->name) &&
                strcasecmp(Utils::getClassBaseName($member->name), Utils::getClassBaseName($class->name)) === 0
            ) {
                return $member;
            }
        }

        return null;
    }

    /**
     * A default written the way the signature declares it, not as the value it
     * evaluates to: rendering the value printed 2 for SORT_STRING, [] for
     * every array and a var_export() dump for an object.
     */
    private static function formatDefault(ReflectionParameter $reflection, mixed $default): string
    {
        // Enum cases are constants too, and arrive here as Enum::Case.
        if ($reflection->isDefaultValueConstant()) {
            return self::constantName($reflection->getDefaultValueConstantName());
        }

        return match (true) {
            is_string($default) => '`' . self::stringLiteral($default) . '`',
            is_bool($default) => $default ? 'true' : 'false',
            $default === null => 'null',
            $default === [] => '[]',
            // A non-empty array or a `new` initializer is code; the parameter's
            // own string form carries the expression as written.
            is_array($default), is_object($default) => '`' . (string)preg_replace(
                '/^.*? = (.*) \]$/s',
                '$1',
                (string)$reflection
            ) . '`',
            // int, float — var_export keeps 0 and 0.0 visible
            default => var_export($default, true),
        };
    }

    /**
     * A default constant's name as it reads in the source. Inside a namespace,
     * reflection reports an unqualified constant under the namespace's name
     * even when PHP falls back to the global one, so a namespaced name that is
     * not defined is the global constant it fell back to.
     */
    private static function constantName(string $name): string
    {
        $separator = strpos($name, '::');
        if ($separator !== false) {
            $class = substr($name, 0, $separator);

            return (in_array(strtolower($class), ['self', 'static', 'parent'], true)
                ? $class
                : Utils::sanitizeClassName($class)) . substr($name, $separator);
        }

        $lastSegment = strrchr($name, '\\');
        if ($lastSegment === false) {
            return $name;
        }

        return defined($name) ? Utils::sanitizeClassName($name) : substr($lastSegment, 1);
    }

    /**
     * A string default as a PHP literal. var_export() writes control
     * characters raw, and a line break ended the table row; a double-quoted
     * string spells them as escapes instead.
     */
    private static function stringLiteral(string $value): string
    {
        return preg_match('/[\x00-\x1f\x7f]/', $value) === 1
            ? '"' . addcslashes($value, "\0..\37\177\"\\\$") . '"'
            : var_export($value, true);
    }

    /**
     * Tries to find out if the type of the given parameter. Will
     * return empty string if not possible.
     *
     * @example
     * ```php
     * <code>
     *  <?php
     *      $reflector = new \ReflectionClass('MyClass');
     *      foreach($reflector->getMethods() as $method ) {
     *          foreach($method->getParameters() as $param) {
     *              $name = $param->getName();
     *              $type = Reflector::getParamType($param);
     *              printf("%s = %s\n", $name, $type);
     *          }
     *      }
     * </code>
     * ```
     */
    public static function getParamType(ReflectionParameter $refParam): string
    {
        $type = self::reflectionTypeNode($refParam->getType(), $refParam->getDeclaringClass()?->getName() ?? '');

        return $type === null ? '' : TypeResolver::print($type);
    }

    /**
     * A reflected type as a type node: native types bare, class references
     * fully qualified with a leading backslash, nullability spelled out.
     *
     * Reading the type off the reflection API rather than off ReflectionParameter's
     * string export is what makes union, intersection and nullable types come out
     * intact — the export is a debug format and states only one type. As a node
     * it also prints with the parentheses a "(A&B)|null" needs, which joining
     * the members as strings left out.
     */
    private static function reflectionTypeNode(?ReflectionType $type, string $declaringClass): ?TypeNode
    {
        return $type === null ? null : self::typeNodeOf($type, $declaringClass);
    }

    private static function typeNodeOf(ReflectionType $type, string $declaringClass): TypeNode
    {
        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            $members = array_map(
                static fn (ReflectionType $member): TypeNode => self::typeNodeOf($member, $declaringClass),
                $type->getTypes()
            );

            return $type instanceof ReflectionUnionType
                ? new UnionTypeNode($members)
                : new IntersectionTypeNode($members);
        }

        /** @var \ReflectionNamedType $type */
        $name = $type->getName();

        // PHP 8.5 resolves a declared "self" itself; earlier versions report the
        // keyword, which names the declaring class.
        if ($name === 'self' && $declaringClass !== '') {
            $node = new IdentifierTypeNode(Utils::sanitizeClassName($declaringClass));
        } else {
            $node = new IdentifierTypeNode(Utils::isNativeType($name) ? $name : Utils::sanitizeClassName($name));
        }

        // getName() drops the "?", and PHP collapses "Foo|null" to the same
        // nullable named type, so both spellings have to be restored here.
        // "mixed" and "null" already admit null by definition.
        if ($type->allowsNull() && !in_array(strtolower($name), ['mixed', 'null'], true)) {
            return new UnionTypeNode([$node, new IdentifierTypeNode('null')]);
        }

        return $node;
    }

    /**
     * @param string|bool|array|mixed $def
     */
    private function getTypeFromVal(mixed $def): string
    {
        if (is_string($def)) {
            return 'string';
        }

        if (is_bool($def)) {
            return 'bool';
        }

        if (is_array($def)) {
            return 'array';
        }

        return 'mixed';
    }

    /**
     * Document only methods with one of these visibilities: public, protected,
     * abstract, final. An unknown name is rejected when the class is reflected.
     *
     * @param list<string> $visibilityFilter
     */
    public function setVisibilityFilter(array $visibilityFilter): self
    {
        $this->visibilityFilter = $visibilityFilter;

        return $this;
    }

    /**
     * Document only methods whose name matches this full regular expression
     * (delimiters included, e.g. "/^get/"); an empty string documents all.
     *
     * @throws \InvalidArgumentException when the pattern does not compile,
     *                                   with PHP's reason in the message
     */
    public function setMethodRegex(string $methodRegex): self
    {
        // preg_match() returns false rather than throwing on a malformed pattern,
        // and array_filter() reads false as "exclude" — so an unvalidated regex
        // dropped every method from every class and still exited 0.
        if ($methodRegex !== '') {
            // The reason comes from the warning, not from preg_last_error_msg():
            // a pattern that fails to *compile* never sets the PCRE runtime error
            // code, so that function reports whatever is left over from before —
            // "No error" or "Internal error" depending on the PHP build. The
            // warning carries the only useful text ("Delimiter must not be
            // alphanumeric...") and is what a user needs to fix the argument.
            // Every compile failure raises that warning, so turning it into an
            // ErrorException is the whole check.
            set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
                throw new ErrorException($message, 0, $severity, $file, $line);
            });

            try {
                preg_match($methodRegex, '');
            } catch (ErrorException $e) {
                throw new InvalidArgumentException(
                    sprintf('Invalid method regex "%s": %s', $methodRegex, $e->getMessage()),
                    0,
                    $e
                );
            } finally {
                restore_error_handler();
            }
        }

        $this->methodRegex = $methodRegex;

        return $this;
    }
}
