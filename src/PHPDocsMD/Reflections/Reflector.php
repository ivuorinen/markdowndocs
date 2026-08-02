<?php

declare(strict_types=1);

namespace PHPDocsMD\Reflections;

use InvalidArgumentException;
use PHPDocsMD\DocInfo;
use PHPDocsMD\DocInfoExtractor;
use PHPDocsMD\Entities\ClassEntity;
use PHPDocsMD\Entities\ClassEntityFactory;
use PHPDocsMD\Entities\FunctionEntity;
use PHPDocsMD\Entities\ParamEntity;
use PHPDocsMD\FunctionFinder;
use PHPDocsMD\UseInspector;
use PHPDocsMD\Utils;
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
 * @package PHPDocsMD
 */
class Reflector
{
    private const VISIBILITIES = [
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
    private array $visibilityFilter = [];
    private string $methodRegex = '';

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
     * @throws \ReflectionException
     */
    public function getClassEntity(): ClassEntity
    {
        $classReflection = new ReflectionClass($this->className);
        $classEntity = $this->classEntityFactory->create($classReflection);

        $classEntity->setFunctions($this->getClassFunctions($classEntity, $classReflection));

        return $classEntity;
    }

    /**
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
            $methodReflections = array_filter(
                $methodReflections,
                fn (ReflectionMethod $reflectionMethod) => preg_match(
                    $this->methodRegex,
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
     * @return \PHPDocsMD\Entities\FunctionEntity|false
     * @throws \ReflectionException
     */
    protected function createFunctionEntity(
        ReflectionMethod $method,
        ClassEntity $class,
        array $useStatements
    ): bool|FunctionEntity {
        $func = new FunctionEntity();
        $docInfo = $this->docInfoExtractor->extractInfo($method);
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

        $returnType = $this->getReturnType($docInfo, $method, $func, $useStatements);

        $func
            ->setReturnType($returnType)
            ->setParams($this->getParams($method, $docInfo))
            ->setIsStatic($method->isStatic())
            ->setVisibility($method->isPublic() ? 'public' : 'protected')
            ->setAbstract($method->isAbstract())
            ->setClass($class->getName())
            ->setIsReturningNativeClass(Utils::isNativeClassReference($returnType));

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

    private function getReturnType(
        DocInfo $docInfo,
        ReflectionMethod $method,
        FunctionEntity $func,
        array $useStatements
    ): string {
        $returnType = $docInfo->getReturnType();

        if (in_array($returnType, ['self', 'static', '$this'], true)) {
            $returnType = Utils::sanitizeClassName($method->getDeclaringClass()->getName());
        }

        // $func->getReturnType() is still FunctionEntity's initialiser, the literal
        // 'void', so comparing against it only ever excluded a docblock that says
        // "@return void" — the one case where discarding the declared type states
        // the opposite of the truth. The declaration is authoritative, and
        // getReturnTypeFromMethod() falls back to the docblock when it is empty.
        if ($method->hasReturnType()) {
            $returnType = $this->getReturnTypeFromMethod($method, $returnType);
        }

        if (empty($returnType)) {
            $returnType = $this->guessReturnTypeFromFuncName($func->getName());
        } elseif (Utils::isClassReference($returnType) && !$this->classExists($returnType)) {
            $returnType = $this->getReturnTypesArray($returnType, $useStatements);
        }

        return Utils::sanitizeDeclaration(
            $returnType,
            $method->getDeclaringClass()->getNamespaceName()
        );
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

    private function classExists(string $classRef): bool
    {
        // An empty reference reaches the autoloader as "" and makes Composer's
        // ClassLoader warn on an uninitialized string offset.
        $classRef = trim($classRef, '[]\\ ');

        return $classRef !== '' && class_exists($classRef);
    }

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
     * @todo Turn this into a class "FunctionEntityFactory"
     */
    private function createParameterEntity(ReflectionParameter $reflection, array $docs): ParamEntity
    {
        $def = false;
        $hasDefault = $reflection->isDefaultValueAvailable();
        $type = 'mixed';
        $declaredType = self::getParamType($reflection);
        if (!isset($docs['type'])) {
            $docs['type'] = '';
        }

        if ($declaredType && $declaredType !== $docs['type'] &&
            !($declaredType === 'array' && str_ends_with($docs['type'], '[]'))
        ) {
            if ($docs['type'] === '') {
                $docs['type'] = $declaredType;
            } elseif (Utils::getClassBaseName($docs['type']) === Utils::getClassBaseName($declaredType)) {
                // Same class written two ways — the declared type is already resolved.
                $docs['type'] = $declaredType;
            } else {
                $docs['type'] = self::mergeTypeDeclarations($docs['type'], $declaredType);
            }
        }

        if ($hasDefault) {
            $def = $reflection->getDefaultValue();
            $type = $this->getTypeFromVal($def);
            if (is_string($def)) {
                $def = "`'$def'`";
            } elseif (is_bool($def)) {
                $def = $def ? 'true' : 'false';
            } elseif ($def === null) {
                $def = 'null';
            } elseif (is_array($def)) {
                $def = '[]';
            } else {
                // int, float — var_export keeps 0 and 0.0 visible
                $def = var_export($def, true);
            }
        }

        // "..." and "&" are separate reflection flags and getName() returns the
        // bare name, so without them the rendered signature is not the signature:
        // "f(...$a)" was documented as "f($a)", and "f(array &$out)" as one that
        // does not modify its argument.
        $varName = match (true) {
            $reflection->isVariadic() => '...$',
            $reflection->isPassedByReference() => '&$',
            default => '$',
        } . $reflection->getName();

        // $docs always carries at least the 'type' key set above, so the
        // "no docblock" arm that used to sit here could never run; an
        // undocumented parameter takes the same path and picks up its inferred
        // type from the elseif below.
        $docs['default'] = $def;
        if ($type === 'mixed' && $def === 'null' && str_starts_with($docs['type'], '\\')) {
            $type = false;
        }

        if ($type && $def &&
            !empty($docs['type']) &&
            $docs['type'] !== $type &&
            !str_contains($docs['type'], '|')
        ) {
            if (substr($docs['type'], (int)strpos($docs['type'], '\\')) ===
                substr($declaredType, (int)strpos($declaredType, '\\'))
            ) {
                $docs['type'] = $declaredType;
            } else {
                $docs['type'] = ($type === 'mixed' ? '' : $type . '|') . $docs['type'];
            }
        } elseif ($type && empty($docs['type'])) {
            $docs['type'] = $type;
        }

        $param = new ParamEntity();
        $param->setDescription($docs['description'] ?? '');
        // Reflection, not the docblock: the docblock may spell the name without
        // the marker the signature carries.
        $param->setName($varName);
        if ($hasDefault) {
            $param->setDefault((string)$def);
        }
        // Pipes used to be rewritten as slashes because an unescaped pipe broke
        // out of its markdown cell. MDTableGenerator::escapeCell() handles that
        // now, so union members can keep the separator PHP actually uses.
        $param->setType(
            empty($docs['type'])
                ? 'mixed'
                : (string)preg_replace('/\s*\|\s*/', ' | ', str_replace('\\\\', '\\', $docs['type']))
        );

        return $param;
    }

    /**
     * Union members from the given declarations, in order, without repeats.
     *
     * The two producers of a type declaration here do not agree on spacing —
     * Utils::sanitizeDeclaration() joins with " | ", formatReflectionType() with
     * "|" — so members are compared trimmed and case-insensitively. Comparing
     * the declarations as whole strings made "int | string" and "int|string"
     * read as disjoint and concatenated them into "int | string | int | string".
     */
    private static function mergeTypeDeclarations(string ...$declarations): string
    {
        $members = [];
        foreach ($declarations as $declaration) {
            foreach (explode('|', $declaration) as $member) {
                $member = trim($member);
                if ($member !== '') {
                    $members[strtolower($member)] = $member;
                }
            }
        }

        return implode('|', $members);
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
        return self::formatReflectionType(
            $refParam->getType(),
            $refParam->getDeclaringClass()?->getName() ?? ''
        );
    }

    /**
     * Render a reflected type the way the generated markdown expects it: native
     * types bare, class references fully qualified with a leading backslash,
     * union and intersection members joined, and nullability spelled out.
     *
     * Reading the type off the reflection API rather than off ReflectionParameter's
     * string export is what makes union, intersection and nullable types come out
     * intact — the export is a debug format and states only one type.
     */
    private static function formatReflectionType(?ReflectionType $type, string $declaringClass = ''): string
    {
        if ($type === null) {
            return '';
        }

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            // Utils::sanitizeDeclaration() splits unions on "|" and re-spaces them;
            // it has no notion of "&", so intersections are spaced here and its
            // "contains a space" test then leaves them alone.
            $glue = $type instanceof ReflectionUnionType ? '|' : ' & ';

            return implode(
                $glue,
                array_map(
                    static fn (ReflectionType $member) => self::formatReflectionType($member, $declaringClass),
                    $type->getTypes()
                )
            );
        }

        /** @var \ReflectionNamedType $type */
        $name = $type->getName();

        if ($name === 'self' && $declaringClass !== '') {
            $rendered = Utils::sanitizeClassName($declaringClass);
        } else {
            $rendered = Utils::isNativeType($name) ? $name : Utils::sanitizeClassName($name);
        }

        // getName() drops the "?", and PHP collapses "Foo|null" to the same
        // nullable named type, so both spellings have to be restored here.
        // "mixed" and "null" already admit null by definition.
        if ($type->allowsNull() && !in_array(strtolower($name), ['mixed', 'null'], true)) {
            $rendered .= '|null';
        }

        return $rendered;
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

    public function setVisibilityFilter(array $visibilityFilter): self
    {
        $this->visibilityFilter = $visibilityFilter;

        return $this;
    }

    public function setMethodRegex(string $methodRegex): self
    {
        // preg_match() returns false rather than throwing on a malformed pattern,
        // and array_filter() reads false as "exclude" — so an unvalidated regex
        // dropped every method from every class and still exited 0.
        if ($methodRegex !== '' && @preg_match($methodRegex, '') === false) {
            throw new InvalidArgumentException(
                sprintf('Invalid method regex "%s": %s', $methodRegex, preg_last_error_msg())
            );
        }

        $this->methodRegex = $methodRegex;

        return $this;
    }

    public function getReturnTypeFromMethod(ReflectionMethod $method, string $returnType): string
    {
        $declared = self::formatReflectionType($method->getReturnType(), $method->class);

        return $declared === '' ? $returnType : $declared;
    }

    /**
     * Resolve a docblock type against the importing file's use statements.
     *
     * @param array<string, string> $useStatements local name => imported class
     */
    public function getReturnTypesArray(string $returnType, array $useStatements): string
    {
        $isReferenceToArrayOfObjects = str_ends_with($returnType, '[]') ? '[]' : '';
        if ($isReferenceToArrayOfObjects) {
            $returnType = substr($returnType, 0, -2);
        }

        // Keyed by the name the file writes, so an alias resolves to the class it
        // was aliased from rather than falling through to the current namespace.
        $localName = Utils::getClassBaseName($returnType);
        if (isset($useStatements[$localName])) {
            $returnType = $useStatements[$localName];
        }

        if ($isReferenceToArrayOfObjects) {
            $returnType .= '[]';
        }

        return $returnType;
    }
}
