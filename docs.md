## Table of contents

- [\PHPDocsMD\DocInfo](#class-phpdocsmd-docinfo)
- [\PHPDocsMD\DocInfoExtractor](#class-phpdocsmd-docinfoextractor)
- [\PHPDocsMD\FunctionFinder](#class-phpdocsmd-functionfinder)
- [\PHPDocsMD\MDTableGenerator](#class-phpdocsmd-mdtablegenerator)
- [\PHPDocsMD\TableGenerator (interface)](#interface-phpdocsmd-tablegenerator)
- [\PHPDocsMD\UseInspector](#class-phpdocsmd-useinspector)
- [\PHPDocsMD\Utils](#class-phpdocsmd-utils)
- [\PHPDocsMD\Console\CLI](#class-phpdocsmd-console-cli)
- [\PHPDocsMD\Console\PHPDocsMDCommand](#class-phpdocsmd-console-phpdocsmdcommand)
- [\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity)
- [\PHPDocsMD\Entities\ClassEntityFactory](#class-phpdocsmd-entities-classentityfactory)
- [\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity)
- [\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity)
- [\PHPDocsMD\Entities\ParamEntity](#class-phpdocsmd-entities-paramentity)
- [\PHPDocsMD\Reflections\Reflector](#class-phpdocsmd-reflections-reflector)

<hr /><a id="class-phpdocsmd-docinfo"></a>

### Class: \PHPDocsMD\DocInfo

> Class containing information about a function/class that's being made available via a comment block

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>array</em> <strong>$data=[])</strong> : <em>void</em> |
| public | <strong>getDeprecationMessage()</strong> : <em>string</em> |
| public | <strong>getDescription()</strong> : <em>string</em> |
| public | <strong>getExample()</strong> : <em>string</em> |
| public | <strong>getParameterInfo(</strong><em>string</em> <strong>$name)</strong> : <em>array</em> |
| public | <strong>getReturnType()</strong> : <em>string</em> |
| public | <strong>getSee()</strong> : <em>array</em> |
| public | <strong>isDeprecated()</strong> : <em>bool</em> |
| public | <strong>isInternal()</strong> : <em>bool</em> |
| public | <strong>shouldBeIgnored()</strong> : <em>bool</em> |
| public | <strong>shouldInheritDoc()</strong> : <em>bool</em> |


<hr /><a id="class-phpdocsmd-docinfoextractor"></a>

### Class: \PHPDocsMD\DocInfoExtractor

> Class that can extract information from a function/class comment

| Visibility | Function |
|:-----------|:---------|
| public | <strong>applyInfoToEntity(</strong><em>[\ReflectionMethod](https://php.net/manual/en/class.reflectionmethod.php)  \| [\ReflectionClass](https://php.net/manual/en/class.reflectionclass.php) </em> <strong>$reflection</strong>, <em>[\PHPDocsMD\DocInfo](#class-phpdocsmd-docinfo) </em> <strong>$docInfo</strong>, <em>[\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity) </em> <strong>$code)</strong> : <em>void</em> |
| public | <strong>extractInfo(</strong><em>[\ReflectionMethod](https://php.net/manual/en/class.reflectionmethod.php)  \| [\ReflectionClass](https://php.net/manual/en/class.reflectionclass.php) </em> <strong>$reflection)</strong> : <em>[\PHPDocsMD\DocInfo](#class-phpdocsmd-docinfo) </em> |


<hr /><a id="class-phpdocsmd-functionfinder"></a>

### Class: \PHPDocsMD\FunctionFinder

> Find a specific function in a class or an array of classes

| Visibility | Function |
|:-----------|:---------|
| public | <strong>find(</strong><em>string</em> <strong>$methodName</strong>, <em>string</em> <strong>$className)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity)  \| false</em> |
| public | <strong>findInClasses(</strong><em>string</em> <strong>$methodName</strong>, <em>array</em> <strong>$classes)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity)  \| false</em> |


<hr /><a id="class-phpdocsmd-mdtablegenerator"></a>

### Class: \PHPDocsMD\MDTableGenerator

> Class that can create a markdown-formatted table describing class functions referred to via FunctionEntity objects

###### Example
```php
<?php
    $generator = new PHPDocsMD\MDTableGenerator();
    $generator->openTable();
    foreach($classEntity->getFunctions() as $func) {
            $generator->addFunc( $func );
    }
    echo $generator->getTable();
```

| Visibility | Function |
|:-----------|:---------|
| public | <strong>addFunc(</strong><em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity) </em> <strong>$func</strong>, <em>bool</em> <strong>$includeSee=false)</strong> : <em>string</em><br /><em>Generates a markdown formatted table row with information about given function. Then adds the row to the table and returns the markdown formatted string.</em> |
| public | <strong>appendExamplesToEndOfTable(</strong><em>bool</em> <strong>$toggle)</strong> : <em>void</em><br /><em>All example comments found while generating the table will be appended to the end of the table. Setting $toggle to false prevents this behaviour.</em> |
| public | <strong>doDeclareAbstraction(</strong><em>bool</em> <strong>$toggle)</strong> : <em>void</em><br /><em>Toggle whether methods being abstract (or part of an interface) should be declared as abstract in the table</em> |
| public static | <strong>formatExampleComment(</strong><em>string</em> <strong>$example)</strong> : <em>string</em><br /><em>Create a markdown-formatted code view out of an example comment</em> |
| public | <strong>getTable()</strong> : <em>string</em> |
| public | <strong>openTable()</strong> : <em>void</em><br /><em>Begin generating a new markdown-formatted table</em> |


*This class implements [\PHPDocsMD\TableGenerator](#interface-phpdocsmd-tablegenerator)*

<hr /><a id="interface-phpdocsmd-tablegenerator"></a>

### Interface: \PHPDocsMD\TableGenerator

> Any class that can create a markdown-formatted table describing class functions referred to via FunctionEntity objects should implement this interface.

| Visibility | Function |
|:-----------|:---------|
| public | <strong>addFunc(</strong><em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity) </em> <strong>$func</strong>, <em>bool</em> <strong>$includeSee=false)</strong> : <em>string</em><br /><em>Generates a markdown formatted table row with information about given function. Then adds the row to the table and returns the markdown formatted string.</em> |
| public | <strong>appendExamplesToEndOfTable(</strong><em>bool</em> <strong>$toggle)</strong> : <em>void</em><br /><em>All example comments found while generating the table will be appended to the end of the table. Set $toggle to false to prevent this behaviour</em> |
| public | <strong>doDeclareAbstraction(</strong><em>bool</em> <strong>$toggle)</strong> : <em>void</em><br /><em>Toggle whether methods being abstract (or part of an interface) should be declared as abstract in the table</em> |
| public static | <strong>formatExampleComment(</strong><em>string</em> <strong>$example)</strong> : <em>string</em><br /><em>Create a markdown-formatted code view out of an example comment.</em> |
| public | <strong>getTable()</strong> : <em>string</em> |
| public | <strong>openTable()</strong> : <em>void</em><br /><em>Begin generating a new markdown-formatted table</em> |


<hr /><a id="class-phpdocsmd-useinspector"></a>

### Class: \PHPDocsMD\UseInspector

> Class that can extract all use statements in a file

| Visibility | Function |
|:-----------|:---------|
| public | <strong>getUseStatements(</strong><em>[\ReflectionClass](https://php.net/manual/en/class.reflectionclass.php) </em> <strong>$reflectionClass)</strong> : <em>array</em> |
| public | <strong>getUseStatementsInFile(</strong><em>string</em> <strong>$filePath)</strong> : <em>array</em> |
| public | <strong>getUseStatementsInString(</strong><em>string</em> <strong>$content)</strong> : <em>array</em><br /><em>Collect the classes a piece of PHP imports. Keyed by the name the importing file actually writes — the alias when there is one, the last segment otherwise — because that is the name a docblock type has to be looked up by. Tokenising rather than splitting on the string "use" is what keeps prose ("because"), closure captures (`function () use ($x)`) and comment text out of the result, and what makes aliases and group imports resolvable: use A\B;             -> ['B' => '\A\B'] use A\B as C;        -> ['C' => '\A\B'] use A\{B, C};        -> ['B' => '\A\B', 'C' => '\A\C'] use function strlen; -> skipped   (not a class)</em> |


<hr /><a id="class-phpdocsmd-utils"></a>

### Class: \PHPDocsMD\Utils

> Utilities.

| Visibility | Function |
|:-----------|:---------|
| public static | <strong>getClassBaseName(</strong><em>string</em> <strong>$fullClassName)</strong> : <em>string</em> |
| public static | <strong>isClassReference(</strong><em>string</em> <strong>$typeDeclaration)</strong> : <em>bool</em> |
| public static | <strong>isNativeClassReference(</strong><em>string</em> <strong>$typeDeclaration)</strong> : <em>bool</em> |
| public static | <strong>isNativeType(</strong><em>string</em> <strong>$type=`''`)</strong> : <em>bool</em> |
| public static | <strong>sanitizeClassName(</strong><em>string</em> <strong>$name)</strong> : <em>string</em> |
| public static | <strong>sanitizeDeclaration(</strong><em>string</em> <strong>$typeDeclaration</strong>, <em>string</em> <strong>$currentNameSpace</strong>, <em>string</em> <strong>$delimiter=`'\|'`)</strong> : <em>string</em> |


<hr /><a id="class-phpdocsmd-console-cli"></a>

### Class: \PHPDocsMD\Console\CLI

> Command line interface used to extract markdown-formatted documentation from classes

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct()</strong> : <em>void</em> |
| public | <strong>run(</strong><em>\Symfony\Component\Console\Input\InputInterface \| null</em> <strong>$input=null</strong>, <em>\Symfony\Component\Console\Output\OutputInterface \| null</em> <strong>$output=null)</strong> : <em>int</em> |


*This class extends \Symfony\Component\Console\Application*

*This class implements \Symfony\Contracts\Service\ResetInterface*

<hr /><a id="class-phpdocsmd-console-phpdocsmdcommand"></a>

### Class: \PHPDocsMD\Console\PHPDocsMDCommand

> Console command used to extract markdown-formatted documentation from classes

| Visibility | Function |
|:-----------|:---------|
| protected | <strong>buildTableGenerator(</strong><em>string</em> <strong>$tableGeneratorSlug=`'default'`)</strong> : <em>[\PHPDocsMD\TableGenerator](#interface-phpdocsmd-tablegenerator) </em> |
| protected | <strong>configure()</strong> : <em>void</em> |
| protected | <strong>execute(</strong><em>\Symfony\Component\Console\Input\InputInterface</em> <strong>$input</strong>, <em>\Symfony\Component\Console\Output\OutputInterface</em> <strong>$output)</strong> : <em>int</em> |


*This class extends \Symfony\Component\Console\Command\Command*

*This class implements \Symfony\Component\Console\Command\SignalableCommandInterface*

<hr /><a id="class-phpdocsmd-entities-classentity"></a>

### Class: \PHPDocsMD\Entities\ClassEntity

> Object describing a class or an interface

| Visibility | Function |
|:-----------|:---------|
| public | <strong>generateAnchor()</strong> : <em>string</em><br /><em>Generates an anchor link out of the generated title (see generateTitle)</em> |
| public | <strong>generateTitle(</strong><em>string</em> <strong>$format=`'%label%: %name% %extra%'`)</strong> : <em>string</em><br /><em>Generate a title describing the class this object is referring to</em> |
| public | <strong>getExtends()</strong> : <em>string</em> |
| public | <strong>getFunctions()</strong> : <em>array</em> |
| public | <strong>getInterfaces()</strong> : <em>array</em> |
| public | <strong>hasIgnoreTag(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>hasInternalTag(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>isAbstract(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>isEnum(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>isInterface(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>isSame(</strong><em>object \| string</em> <strong>$class)</strong> : <em>bool</em><br /><em>Check whether this object is referring to given class name or object instance</em> |
| public | <strong>isTrait(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>setExtends(</strong><em>string</em> <strong>$extends)</strong> : <em>[\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity) </em> |
| public | <strong>setFunctions(</strong><em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity) []</em> <strong>$functions)</strong> : <em>[\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity) </em> |
| public | <strong>setInterfaces(</strong><em>array</em> <strong>$implements)</strong> : <em>[\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity) </em> |
| public | <strong>setName(</strong><em>string</em> <strong>$name)</strong> : <em>[\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity) </em> |


*This class extends [\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity)*

<hr /><a id="class-phpdocsmd-entities-classentityfactory"></a>

### Class: \PHPDocsMD\Entities\ClassEntityFactory

> Class capable of creating ClassEntity objects

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>[\PHPDocsMD\DocInfoExtractor](#class-phpdocsmd-docinfoextractor) </em> <strong>$docInfoExtractor)</strong> : <em>void</em> |
| public | <strong>create(</strong><em>[\ReflectionClass](https://php.net/manual/en/class.reflectionclass.php) </em> <strong>$reflection)</strong> : <em>[\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity) </em> |


<hr /><a id="class-phpdocsmd-entities-codeentity"></a>

### Class: \PHPDocsMD\Entities\CodeEntity

> Object describing a piece of code

| Visibility | Function |
|:-----------|:---------|
| public | <strong>getDeprecationMessage()</strong> : <em>string</em> |
| public | <strong>getDescription()</strong> : <em>string</em> |
| public | <strong>getExample()</strong> : <em>string</em> |
| public | <strong>getName()</strong> : <em>string</em> |
| public | <strong>getSee()</strong> : <em>array</em> |
| public | <strong>isDeprecated(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>isInternal(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool \| null</em> |
| public | <strong>setDeprecationMessage(</strong><em>string</em> <strong>$deprecationMessage)</strong> : <em>[\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity) </em> |
| public | <strong>setDescription(</strong><em>string</em> <strong>$description)</strong> : <em>[\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity) </em> |
| public | <strong>setExample(</strong><em>string</em> <strong>$example)</strong> : <em>[\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity) </em> |
| public | <strong>setName(</strong><em>string</em> <strong>$name)</strong> : <em>[\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity) </em> |
| public | <strong>setSee(</strong><em>array</em> <strong>$see)</strong> : <em>static</em><br /><em>Returns static so subclasses inherit this without having to override it only to keep their own return type — ClassEntity and FunctionEntity each carried a verbatim copy of this method and a shadowing $see property.</em> |


<hr /><a id="class-phpdocsmd-entities-functionentity"></a>

### Class: \PHPDocsMD\Entities\FunctionEntity

> Object describing a function

| Visibility | Function |
|:-----------|:---------|
| public | <strong>getClass()</strong> : <em>string</em> |
| public | <strong>getParams()</strong> : <em>array</em> |
| public | <strong>getReturnType()</strong> : <em>string</em> |
| public | <strong>getVisibility()</strong> : <em>string</em> |
| public | <strong>hasParams()</strong> : <em>bool</em> |
| public | <strong>isAbstract(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>isReturningNativeClass(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>isStatic(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>setAbstract(</strong><em>bool</em> <strong>$abstract)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity) </em> |
| public | <strong>setClass(</strong><em>string</em> <strong>$class)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity) </em> |
| public | <strong>setIsReturningNativeClass(</strong><em>bool</em> <strong>$isReturningNativeClass)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity) </em> |
| public | <strong>setIsStatic(</strong><em>bool</em> <strong>$isStatic)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity) </em> |
| public | <strong>setParams(</strong><em>[\PHPDocsMD\Entities\ParamEntity](#class-phpdocsmd-entities-paramentity) []</em> <strong>$params)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity) </em> |
| public | <strong>setReturnType(</strong><em>string</em> <strong>$returnType)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity) </em> |
| public | <strong>setVisibility(</strong><em>string</em> <strong>$visibility)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity) </em> |


*This class extends [\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity)*

<hr /><a id="class-phpdocsmd-entities-paramentity"></a>

### Class: \PHPDocsMD\Entities\ParamEntity

> Object describing a function parameter

| Visibility | Function |
|:-----------|:---------|
| public | <strong>getDefault()</strong> : <em>string</em> |
| public | <strong>getNativeClassType()</strong> : <em>string \| null</em> |
| public | <strong>getType()</strong> : <em>string</em> |
| public | <strong>hasDefault()</strong> : <em>bool</em><br /><em>Whether a default value was declared at all. Distinct from getDefault() being truthy: "0" and "" are perfectly good defaults.</em> |
| public | <strong>setDefault(</strong><em>string</em> <strong>$default)</strong> : <em>[\PHPDocsMD\Entities\ParamEntity](#class-phpdocsmd-entities-paramentity) </em> |
| public | <strong>setType(</strong><em>string</em> <strong>$type)</strong> : <em>[\PHPDocsMD\Entities\ParamEntity](#class-phpdocsmd-entities-paramentity) </em> |


*This class extends [\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity)*

<hr /><a id="class-phpdocsmd-reflections-reflector"></a>

### Class: \PHPDocsMD\Reflections\Reflector

> Class that can compute ClassEntity objects out of real classes

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>string</em> <strong>$className</strong>, <em>[\PHPDocsMD\FunctionFinder](#class-phpdocsmd-functionfinder)  \| null</em> <strong>$functionFinder=null)</strong> : <em>void</em> |
| public | <strong>getClassEntity()</strong> : <em>[\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity) </em> |
| public static | <strong>getParamType(</strong><em>[\ReflectionParameter](https://php.net/manual/en/class.reflectionparameter.php) </em> <strong>$refParam)</strong> : <em>string</em><br /><em>Tries to find out if the type of the given parameter. Will return empty string if not possible.</em> |
| public | <strong>getReturnTypeFromMethod(</strong><em>[\ReflectionMethod](https://php.net/manual/en/class.reflectionmethod.php) </em> <strong>$method</strong>, <em>string</em> <strong>$returnType)</strong> : <em>string</em> |
| public | <strong>getReturnTypesArray(</strong><em>string</em> <strong>$returnType</strong>, <em>array</em> <strong>$useStatements)</strong> : <em>string</em><br /><em>Resolve a docblock type against the importing file's use statements.</em> |
| public | <strong>setMethodRegex(</strong><em>string</em> <strong>$methodRegex)</strong> : <em>[\PHPDocsMD\Reflections\Reflector](#class-phpdocsmd-reflections-reflector) </em> |
| public | <strong>setVisibilityFilter(</strong><em>array</em> <strong>$visibilityFilter)</strong> : <em>[\PHPDocsMD\Reflections\Reflector](#class-phpdocsmd-reflections-reflector) </em> |
| protected | <strong>createFunctionEntity(</strong><em>[\ReflectionMethod](https://php.net/manual/en/class.reflectionmethod.php) </em> <strong>$method</strong>, <em>[\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity) </em> <strong>$class</strong>, <em>array</em> <strong>$useStatements)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity)  \| bool</em> |
| protected | <strong>shouldIgnoreFunction(</strong><em>[\PHPDocsMD\DocInfo](#class-phpdocsmd-docinfo) </em> <strong>$info</strong>, <em>[\ReflectionMethod](https://php.net/manual/en/class.reflectionmethod.php) </em> <strong>$method</strong>, <em>[\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity) </em> <strong>$class)</strong> : <em>bool</em> |
###### Examples of Reflector::getParamType()
```php
<?php
    $reflector = new \ReflectionClass('MyClass');
    foreach($reflector->getMethods() as $method ) {
        foreach($method->getParameters() as $param) {
            $name = $param->getName();
            $type = Reflector::getParamType($param);
            printf("%s = %s\n", $name, $type);
        }
    }
```


