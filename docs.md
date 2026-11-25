## Table of contents

- [\PHPDocsMD\DocInfo](#class-phpdocsmd-docinfo)
- [\PHPDocsMD\DocInfoExtractor](#class-phpdocsmd-docinfoextractor)
- [\PHPDocsMD\MDTableGenerator](#class-phpdocsmd-mdtablegenerator)
- [\PHPDocsMD\TableGenerator (interface)](#interface-phpdocsmd-tablegenerator)
- [\PHPDocsMD\TypeResolver](#class-phpdocsmd-typeresolver)
- [\PHPDocsMD\UseInspector](#class-phpdocsmd-useinspector)
- [\PHPDocsMD\Utils](#class-phpdocsmd-utils)
- [\PHPDocsMD\Console\CLI](#class-phpdocsmd-console-cli)
- [\PHPDocsMD\Console\PHPDocsMDCommand](#class-phpdocsmd-console-phpdocsmdcommand)
- [\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity)
- [\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity)
- [\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity)
- [\PHPDocsMD\Entities\ParamEntity](#class-phpdocsmd-entities-paramentity)
- [\PHPDocsMD\Reflections\ClassEntityFactory](#class-phpdocsmd-reflections-classentityfactory)
- [\PHPDocsMD\Reflections\FunctionFinder](#class-phpdocsmd-reflections-functionfinder)
- [\PHPDocsMD\Reflections\Reflector](#class-phpdocsmd-reflections-reflector)

<hr /><a id="class-phpdocsmd-docinfo"></a>

### Class: \PHPDocsMD\DocInfo

> Class containing information about a function/class that's being made available via a comment block

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>Tags \| array</em> <strong>$data=[])</strong> : <em>void</em> |
| public | <strong>getDeprecationMessage()</strong> : <em>string</em> |
| public | <strong>getDescription()</strong> : <em>string</em> |
| public | <strong>getExample()</strong> : <em>string</em> |
| public | <strong>getParameterInfo(</strong><em>string</em> <strong>$name)</strong> : <em>array</em><br /><em>What the docblock says about one parameter, looked up as "$name"; an empty array when it says nothing. 'type' is absent for a typeless "@param $name ..." and for one whose type could not be read.</em> |
| public | <strong>getProblems()</strong> : <em>list&lt;string&gt;</em><br /><em>The tags that could not be read, each as "cannot read "<tag>": <reason>".</em> |
| public | <strong>getReturnType()</strong> : <em>string</em> |
| public | <strong>getSee()</strong> : <em>list&lt;string&gt;</em> |
| public | <strong>isDeprecated()</strong> : <em>bool</em> |
| public | <strong>isInternal()</strong> : <em>bool</em> |
| public | <strong>shouldBeIgnored()</strong> : <em>bool</em> |
| public | <strong>shouldInheritDoc()</strong> : <em>bool</em> |


<hr /><a id="class-phpdocsmd-docinfoextractor"></a>

### Class: \PHPDocsMD\DocInfoExtractor

> Class that can extract information from a function/class comment phpstan/phpdoc-parser reads the comment. It knows where a tag's type ends however many spaces the type contains — "array<string, int>", "callable(int): void" — which splitting the line into words never could.

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct()</strong> : <em>void</em><br /><em>Builds one phpdoc-parser instance, reused for every docblock this extractor reads.</em> |
| public | <strong>applyInfoToEntity(</strong><em>[\ReflectionMethod](https://php.net/manual/en/class.reflectionmethod.php) \| [\ReflectionClass](https://php.net/manual/en/class.reflectionclass.php)</em> <strong>$reflection</strong>, <em>[\PHPDocsMD\DocInfo](#class-phpdocsmd-docinfo)</em> <strong>$docInfo</strong>, <em>[\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity)</em> <strong>$code)</strong> : <em>void</em><br /><em>Copy what every entity carries from its docblock — name, description, example, @see references, problems and the internal and deprecated flags — onto the entity. Types are applied by Reflector, which merges them with the declaration.</em> |
| public | <strong>extractInfo(</strong><em>[\ReflectionMethod](https://php.net/manual/en/class.reflectionmethod.php) \| [\ReflectionClass](https://php.net/manual/en/class.reflectionclass.php)</em> <strong>$reflection</strong>, <em>array&lt;string, string&gt;</em> <strong>$imports=[])</strong> : <em>[\PHPDocsMD\DocInfo](#class-phpdocsmd-docinfo)</em><br /><em>Read a class's or method's docblock into a DocInfo. Types come back with every class name fully qualified, resolved through `$imports` and the declaring namespace; template and type alias names declared by the class or method docblock stay as written. An undocumented reflection yields an empty DocInfo.</em> |


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
| public | <strong>addFunc(</strong><em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity)</em> <strong>$func</strong>, <em>bool</em> <strong>$includeSee=false)</strong> : <em>string</em><br /><em>Generates a markdown formatted table row with information about given function. Then adds the row to the table and returns the markdown formatted string.</em> |
| public | <strong>appendExamplesToEndOfTable(</strong><em>bool</em> <strong>$toggle)</strong> : <em>void</em><br /><em>All example comments found while generating the table will be appended to the end of the table. Setting $toggle to false prevents this behaviour.</em> |
| public | <strong>doDeclareAbstraction(</strong><em>bool</em> <strong>$toggle)</strong> : <em>void</em><br /><em>Toggle whether methods being abstract (or part of an interface) should be declared as abstract in the table</em> |
| public static | <strong>formatExampleComment(</strong><em>string</em> <strong>$example)</strong> : <em>string</em><br /><em>Create a markdown-formatted code view out of an example comment</em> |
| public | <strong>getTable()</strong> : <em>string</em><br /><em>The finished markdown for the rows added since openTable(), with the collected examples appended when appendExamplesToEndOfTable() allows. The command writes it below the class heading and links class names in it per the markup convention above.</em> |
| public | <strong>openTable()</strong> : <em>void</em><br /><em>Begin generating a new markdown-formatted table</em> |


*This class implements [\PHPDocsMD\TableGenerator](#interface-phpdocsmd-tablegenerator)*

<hr /><a id="interface-phpdocsmd-tablegenerator"></a>

### Interface: \PHPDocsMD\TableGenerator

> Any class that can create a markdown-formatted table describing class functions referred to via FunctionEntity objects should implement this interface. Markup convention: once every table is written, the command links each fully qualified class name (\Acme\Foo) found inside an `<em>…</em>` element — to the class's section, or to the PHP manual for a class PHP ships. An `<em>` that directly follows `<br />` is treated as prose and left untouched, so a class name in a description's code span stays as the author wrote it. Wrap types in `<em>`, and introduce descriptions and other prose with `<br />`.

| Visibility | Function |
|:-----------|:---------|
| public | <strong>addFunc(</strong><em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity)</em> <strong>$func</strong>, <em>bool</em> <strong>$includeSee=false)</strong> : <em>string</em><br /><em>Generates a markdown formatted table row with information about given function. Then adds the row to the table and returns the markdown formatted string.</em> |
| public | <strong>appendExamplesToEndOfTable(</strong><em>bool</em> <strong>$toggle)</strong> : <em>void</em><br /><em>All example comments found while generating the table will be appended to the end of the table. Set $toggle to false to prevent this behaviour</em> |
| public | <strong>doDeclareAbstraction(</strong><em>bool</em> <strong>$toggle)</strong> : <em>void</em><br /><em>Toggle whether methods being abstract (or part of an interface) should be declared as abstract in the table</em> |
| public static | <strong>formatExampleComment(</strong><em>string</em> <strong>$example)</strong> : <em>string</em><br /><em>Create a markdown-formatted code view out of an example comment.</em> |
| public | <strong>getTable()</strong> : <em>string</em><br /><em>The finished markdown for the rows added since openTable(), with the collected examples appended when appendExamplesToEndOfTable() allows. The command writes it below the class heading and links class names in it per the markup convention above.</em> |
| public | <strong>openTable()</strong> : <em>void</em><br /><em>Begin generating a new markdown-formatted table</em> |


<hr /><a id="class-phpdocsmd-typeresolver"></a>

### Class: \PHPDocsMD\TypeResolver

> Reads, resolves and writes PHPDoc type expressions. phpstan/phpdoc-parser does the reading and writing. What it cannot know is which names are classes: that depends on the namespace, the imports and the names the docblocks declare themselves, which is what an instance holds.

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>string</em> <strong>$namespace</strong>, <em>array&lt;string, string&gt;</em> <strong>$imports=[]</strong>, <em>string</em> <strong>$declaringClass=`''`</strong>, <em>list&lt;string&gt;</em> <strong>$localNames=[])</strong> : <em>void</em> |
| public static | <strong>members(</strong><em>\PHPStan\PhpDocParser\Ast\Type\TypeNode</em> <strong>$type)</strong> : <em>list&lt;\PHPStan\PhpDocParser\Ast\Type\TypeNode&gt;</em><br /><em>The members of a union, flattened; a nullable type is its type and null.</em> |
| public static | <strong>parse(</strong><em>string</em> <strong>$type)</strong> : <em>\PHPStan\PhpDocParser\Ast\Type\TypeNode</em><br /><em>Read a type expression in PHPStan/Psalm syntax. The whole text must be the type: "int $x" is rejected, not read as "int".</em> |
| public static | <strong>print(</strong><em>\PHPStan\PhpDocParser\Ast\Type\TypeNode</em> <strong>$type)</strong> : <em>string</em><br /><em>Write a type the way the generated markdown writes types. The library's printer chooses the syntax — parentheses, separators — so that what it writes reads back as the same type. It writes unions as "int\|string"; the separators are spaced here, so that a long union can wrap in a table cell, by re-reading the printed type and spacing exactly the union and intersection tokens. A "\|" inside a string literal is part of a different token and stays as it is.</em> |
| public static | <strong>refines(</strong><em>\PHPStan\PhpDocParser\Ast\Type\TypeNode</em> <strong>$narrow</strong>, <em>\PHPStan\PhpDocParser\Ast\Type\TypeNode</em> <strong>$wide)</strong> : <em>bool</em><br /><em>Whether $narrow states $wide, or a narrower case of it: "list<int>" is an array, "class-string<Foo>" a string, "true" a bool, anything a mixed. Both types are expected resolved.</em> |
| public | <strong>resolve(</strong><em>\PHPStan\PhpDocParser\Ast\Type\TypeNode</em> <strong>$type)</strong> : <em>\PHPStan\PhpDocParser\Ast\Type\TypeNode</em><br /><em>The type with every class name in it fully qualified, wherever the name sits: a generic's parameters, a shape's values, a callable's signature.</em> |
| public static | <strong>union(</strong><em>list&lt;\PHPStan\PhpDocParser\Ast\Type\TypeNode&gt;</em> <strong>$members)</strong> : <em>\PHPStan\PhpDocParser\Ast\Type\TypeNode</em><br /><em>One type made of the given members, each once. Class names compare the way PHP compares them, without regard to case.</em> |


<hr /><a id="class-phpdocsmd-useinspector"></a>

### Class: \PHPDocsMD\UseInspector

> Class that can extract all use statements in a file

| Visibility | Function |
|:-----------|:---------|
| public | <strong>getUseStatements(</strong><em>[\ReflectionClass](https://php.net/manual/en/class.reflectionclass.php)</em> <strong>$reflectionClass)</strong> : <em>array&lt;string, string&gt;</em><br /><em>The classes the file declaring this class imports, keyed by the name the file writes them under; empty for a class with no file (an internal one).</em> |
| public | <strong>getUseStatementsInFile(</strong><em>string</em> <strong>$filePath)</strong> : <em>array&lt;string, string&gt;</em><br /><em>The classes a PHP file imports, as getUseStatementsInString() reads them; empty when the file cannot be read.</em> |
| public | <strong>getUseStatementsInString(</strong><em>string</em> <strong>$content)</strong> : <em>array&lt;string, string&gt;</em><br /><em>Collect the classes a piece of PHP imports. Keyed by the name the importing file actually writes — the alias when there is one, the last segment otherwise — because that is the name a docblock type has to be looked up by. Tokenising rather than splitting on the string "use" is what keeps prose ("because"), closure captures (`function () use ($x)`) and comment text out of the result, and what makes aliases and group imports resolvable: use A\B;             -> ['B' => '\A\B'] use A\B as C;        -> ['C' => '\A\B'] use A\{B, C};        -> ['B' => '\A\B', 'C' => '\A\C'] use function strlen; -> skipped   (not a class)</em> |


<hr /><a id="class-phpdocsmd-utils"></a>

### Class: \PHPDocsMD\Utils

> Utilities.

| Visibility | Function |
|:-----------|:---------|
| public static | <strong>getClassBaseName(</strong><em>string</em> <strong>$fullClassName)</strong> : <em>string</em><br /><em>The last segment of a class name: "Foo" for "\Acme\NS\Foo". For class names only — a type expression such as "Collection<Foo>" has no single base name.</em> |
| public static | <strong>isClassReference(</strong><em>string</em> <strong>$typeDeclaration)</strong> : <em>bool</em><br /><em>Whether the declaration names a class. It goes through isNativeType() so that the two cannot disagree: they once normalised the leading backslash differently, and "\string" came out both native and a class reference.</em> |
| public static | <strong>isNativeType(</strong><em>string</em> <strong>$type=`''`)</strong> : <em>bool</em><br /><em>Whether the name is a type keyword rather than a class. No PHP identifier can contain a hyphen, so every hyphenated name is a pseudo-type, including the ones PHPStan and Psalm add after this list was written.</em> |
| public static | <strong>sanitizeClassName(</strong><em>string</em> <strong>$name)</strong> : <em>string</em><br /><em>A class name in the one form the generator compares and prints: one leading backslash, no surrounding spaces or backslashes.</em> |


<hr /><a id="class-phpdocsmd-console-cli"></a>

### Class: \PHPDocsMD\Console\CLI

> Command line interface used to extract markdown-formatted documentation from classes

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct()</strong> : <em>void</em> |
| public | <strong>run(</strong><em>\Symfony\Component\Console\Input\InputInterface \| null</em> <strong>$input=null</strong>, <em>\Symfony\Component\Console\Output\OutputInterface \| null</em> <strong>$output=null)</strong> : <em>int</em><br /><em>Register the generate command and run the application; the exit code is the command's. bin/phpdoc-md calls this with no arguments, which reads argv and writes to the console.</em> |


*This class extends \Symfony\Component\Console\Application*

*This class implements \Symfony\Contracts\Service\ResetInterface*

<hr /><a id="class-phpdocsmd-console-phpdocsmdcommand"></a>

### Class: \PHPDocsMD\Console\PHPDocsMDCommand

> Console command used to extract markdown-formatted documentation from classes

| Visibility | Function |
|:-----------|:---------|
| protected | <strong>buildTableGenerator(</strong><em>string</em> <strong>$tableGeneratorSlug=`'default'`)</strong> : <em>[\PHPDocsMD\TableGenerator](#interface-phpdocsmd-tablegenerator)</em> |
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
| public | <strong>getInterfaces()</strong> : <em>list&lt;string&gt;</em> |
| public | <strong>hasIgnoreTag(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>hasInternalTag(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>isAbstract(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>isEnum(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>isInterface(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>isSame(</strong><em>object \| string</em> <strong>$class)</strong> : <em>bool</em><br /><em>Check whether this object is referring to given class name or object instance</em> |
| public | <strong>isTrait(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>setExtends(</strong><em>string</em> <strong>$extends)</strong> : <em>[\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity)</em><br /><em>Stored with a leading backslash, the form type cells and section anchors compare against.</em> |
| public | <strong>setFunctions(</strong><em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity)[]</em> <strong>$functions)</strong> : <em>[\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity)</em> |
| public | <strong>setInterfaces(</strong><em>list&lt;string&gt;</em> <strong>$implements)</strong> : <em>[\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity)</em><br /><em>Each name is stored with a leading backslash, the form type cells and section anchors compare against.</em> |
| public | <strong>setName(</strong><em>string</em> <strong>$name)</strong> : <em>[\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity)</em><br /><em>Stored with a leading backslash, the form type cells and section anchors compare against.</em> |


*This class extends [\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity)*

<hr /><a id="class-phpdocsmd-entities-codeentity"></a>

### Class: \PHPDocsMD\Entities\CodeEntity

> Object describing a piece of code

| Visibility | Function |
|:-----------|:---------|
| public | <strong>getDeprecationMessage()</strong> : <em>string</em> |
| public | <strong>getDescription()</strong> : <em>string</em> |
| public | <strong>getExample()</strong> : <em>string</em> |
| public | <strong>getName()</strong> : <em>string</em> |
| public | <strong>getProblems()</strong> : <em>list&lt;string&gt;</em><br /><em>What could not be read from the docblock, one message per tag — the documentation it carried is missing from the output, so the command reports each one rather than let it vanish.</em> |
| public | <strong>getSee()</strong> : <em>list&lt;string&gt;</em> |
| public | <strong>isDeprecated(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>isInternal(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool \| null</em> |
| public | <strong>setDeprecationMessage(</strong><em>string</em> <strong>$deprecationMessage)</strong> : <em>[\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity)</em> |
| public | <strong>setDescription(</strong><em>string</em> <strong>$description)</strong> : <em>[\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity)</em> |
| public | <strong>setExample(</strong><em>string</em> <strong>$example)</strong> : <em>[\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity)</em> |
| public | <strong>setName(</strong><em>string</em> <strong>$name)</strong> : <em>[\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity)</em> |
| public | <strong>setProblems(</strong><em>list&lt;string&gt;</em> <strong>$problems)</strong> : <em>static</em> |
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
| public | <strong>isStatic(</strong><em>bool \| null</em> <strong>$toggle=null)</strong> : <em>bool</em> |
| public | <strong>setAbstract(</strong><em>bool</em> <strong>$abstract)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity)</em> |
| public | <strong>setClass(</strong><em>string</em> <strong>$class)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity)</em> |
| public | <strong>setIsStatic(</strong><em>bool</em> <strong>$isStatic)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity)</em> |
| public | <strong>setParams(</strong><em>[\PHPDocsMD\Entities\ParamEntity](#class-phpdocsmd-entities-paramentity)[]</em> <strong>$params)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity)</em> |
| public | <strong>setReturnType(</strong><em>string</em> <strong>$returnType)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity)</em> |
| public | <strong>setVisibility(</strong><em>string</em> <strong>$visibility)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity)</em> |


*This class extends [\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity)*

<hr /><a id="class-phpdocsmd-entities-paramentity"></a>

### Class: \PHPDocsMD\Entities\ParamEntity

> Object describing a function parameter

| Visibility | Function |
|:-----------|:---------|
| public | <strong>getDefault()</strong> : <em>string</em> |
| public | <strong>getType()</strong> : <em>string</em> |
| public | <strong>hasDefault()</strong> : <em>bool</em><br /><em>Whether a default value was declared at all. Distinct from getDefault() being truthy: "0" and "" are perfectly good defaults.</em> |
| public | <strong>setDefault(</strong><em>string</em> <strong>$default)</strong> : <em>[\PHPDocsMD\Entities\ParamEntity](#class-phpdocsmd-entities-paramentity)</em><br /><em>Records the default as it is to be printed, and that one exists: hasDefault() is what tells "no default" from a default of "" or "0".</em> |
| public | <strong>setType(</strong><em>string</em> <strong>$type)</strong> : <em>[\PHPDocsMD\Entities\ParamEntity](#class-phpdocsmd-entities-paramentity)</em> |


*This class extends [\PHPDocsMD\Entities\CodeEntity](#class-phpdocsmd-entities-codeentity)*

<hr /><a id="class-phpdocsmd-reflections-classentityfactory"></a>

### Class: \PHPDocsMD\Reflections\ClassEntityFactory

> Class capable of creating ClassEntity objects

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>[\PHPDocsMD\DocInfoExtractor](#class-phpdocsmd-docinfoextractor)</em> <strong>$docInfoExtractor)</strong> : <em>void</em> |
| public | <strong>create(</strong><em>[\ReflectionClass](https://php.net/manual/en/class.reflectionclass.php)</em> <strong>$reflection)</strong> : <em>[\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity)</em><br /><em>The class's entity with its kind, parent, interfaces and class docblock applied; its methods are added afterwards by Reflector.</em> |


<hr /><a id="class-phpdocsmd-reflections-functionfinder"></a>

### Class: \PHPDocsMD\Reflections\FunctionFinder

> Find a specific function in a class or an array of classes

| Visibility | Function |
|:-----------|:---------|
| public | <strong>find(</strong><em>string</em> <strong>$methodName</strong>, <em>string</em> <strong>$className)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity) \| false</em><br /><em>The method as the class, or the nearest ancestor declaring it, documents it; false when nothing up the chain declares it. Used to resolve , so the entity carries the declaring class's documentation.</em> |
| public | <strong>findInClasses(</strong><em>string</em> <strong>$methodName</strong>, <em>list&lt;string&gt;</em> <strong>$classes)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity) \| false</em><br /><em>The first declaration of the method found in the given classes or their ancestors, in order; false when none declares it.</em> |


<hr /><a id="class-phpdocsmd-reflections-reflector"></a>

### Class: \PHPDocsMD\Reflections\Reflector

> Class that can compute ClassEntity objects out of real classes

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>string</em> <strong>$className</strong>, <em>[\PHPDocsMD\Reflections\FunctionFinder](#class-phpdocsmd-reflections-functionfinder) \| null</em> <strong>$functionFinder=null)</strong> : <em>void</em> |
| public | <strong>getClassEntity()</strong> : <em>[\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity)</em><br /><em>Reflect the class into an entity holding its documented methods — public and protected, narrowed by setVisibilityFilter() and setMethodRegex() — with each method's types merged from its declaration and docblock.</em> |
| public static | <strong>getParamType(</strong><em>[\ReflectionParameter](https://php.net/manual/en/class.reflectionparameter.php)</em> <strong>$refParam)</strong> : <em>string</em><br /><em>Tries to find out if the type of the given parameter. Will return empty string if not possible.</em> |
| public | <strong>setMethodRegex(</strong><em>string</em> <strong>$methodRegex)</strong> : <em>[\PHPDocsMD\Reflections\Reflector](#class-phpdocsmd-reflections-reflector)</em><br /><em>Document only methods whose name matches this full regular expression (delimiters included, e.g. "/^get/"); an empty string documents all.</em> |
| public | <strong>setVisibilityFilter(</strong><em>list&lt;string&gt;</em> <strong>$visibilityFilter)</strong> : <em>[\PHPDocsMD\Reflections\Reflector](#class-phpdocsmd-reflections-reflector)</em><br /><em>Document only methods with one of these visibilities: public, protected, abstract, final. An unknown name is rejected when the class is reflected.</em> |
| protected | <strong>createFunctionEntity(</strong><em>[\ReflectionMethod](https://php.net/manual/en/class.reflectionmethod.php)</em> <strong>$method</strong>, <em>[\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity)</em> <strong>$class</strong>, <em>array&lt;string, string&gt;</em> <strong>$useStatements)</strong> : <em>[\PHPDocsMD\Entities\FunctionEntity](#class-phpdocsmd-entities-functionentity) \| false</em> |
| protected | <strong>shouldIgnoreFunction(</strong><em>[\PHPDocsMD\DocInfo](#class-phpdocsmd-docinfo)</em> <strong>$info</strong>, <em>[\ReflectionMethod](https://php.net/manual/en/class.reflectionmethod.php)</em> <strong>$method</strong>, <em>[\PHPDocsMD\Entities\ClassEntity](#class-phpdocsmd-entities-classentity)</em> <strong>$class)</strong> : <em>bool</em> |
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


