# PHP-Markdown-Documentation-Generator

Documentation is just as important as the code it's referring to. With this command line tool you will be able to write your documentation once, and only once!

This project will write a single-page markdown-formatted API document based on the DocBlock comments in your source code.

### Example

Let's say you have your PHP classes in a directory named "src". Each class has its own file that is named after the class.

```
- src/
  - MyObject.php
  - OtherObject.php
```

Write your code documentation following the standard set by [phpdoc](http://www.phpdoc.org/).

```php
namespace Acme;

/**
 * This is a description of this class
 */
class MyObject {
   
   /**
    * This is a function description
    * @param string $str
    * @param array $arr
    * @return Acme\OtherObject
    */
   public function someFunc($str, $arr=[]) {}
}
```

Then, running `phpdoc-md generate src > api.md` will write your API documentation to the file api.md.

[Here you can see a rendered example](https://github.com/ivuorinen/markdowndocs/blob/main/docs.md)

By default, functions that are public, protected, abstract or final will be a part of the documentation — private functions are never included. Use `--visibility`
to narrow that set. You can also add `@ignore` to any function or class to exclude it from the docs.
Phpdoc-md will try to guess the return type of functions that don't explicitly declare one. The program uses reflection to get as much information as possible
out of the code so that functions that are missing DocBlock comments will still be included in the generated documentation.

### Requirements

- PHP 8.3, 8.4 or 8.5 — every branch currently supported by php.net. Each one is exercised by CI on every push.
- Reflection must be enabled in php.ini
- Each class, interface, trait or enum must be defined in its own `.php` file, with the file name being the same as the type name. Files with
  another extension are not scanned
- The project should use [Composer](https://getcomposer.org/)

### Installation / Usage

This command line tool can be installed using [composer](https://getcomposer.org/).

From the local working directory of the project that you would like to document, run:

```shell
composer require --dev ivuorinen/markdowndocs
```

This will add ivuorinen/markdowndocs to the `require-dev` section of your project's composer.json file. The `phpdoc-md` executable will automatically be copied to
your project's `vendor/bin` directory.

##### Generating docs

The `generate` command generates your project's API documentation file. The command line tool needs to know whether you want to generate docs for a certain
class, or if it should process every class in a specified directory search path.

```shell
# Generate docs for a certain class
./vendor/bin/phpdoc-md generate Acme\\NS\\MyClass 

# Generate docs for several classes (comma separated)
./vendor/bin/phpdoc-md generate Acme\\NS\\MyClass,Acme\\OtherNS\\OtherClass 

# Generate docs for all classes in a source directory
./vendor/bin/phpdoc-md generate includes/src

# Generate docs for all classes in a source directory and send output to the file api.md
./vendor/bin/phpdoc-md generate includes/src > api.md
```

*Note that any class to be documented must be loadable using the autoloader provided by composer.*

##### Options

| Option | Default | Effect |
| --- | --- | --- |
| `--bootstrap`, `-b` | none | PHP file to require before generating documentation |
| `--ignore`, `-i` | none | Comma-separated directory names to skip, at any depth. Matched whole: `--ignore=test` skips `test/`, not `latest/` |
| `--visibility` | `public,protected,abstract,final` | Comma-separated method visibilities to include. Unknown values are rejected; `private` is not supported |
| `--methodRegex` | none | Full regular expression a method name must match to be included |
| `--tableGenerator` | `default` | Slug or fully-qualified class name of a `PHPDocsMD\TableGenerator` implementation. Unknown values are rejected |
| `--see` | off | Include `@see` entries in the generated markdown |
| `--no-internal` | off | Skip classes and functions tagged `@internal` |
| `--no-examples` | off | Omit `@example` blocks that would otherwise follow each function table |

```shell
# Only public methods, only those named like a getter, including @see references
./vendor/bin/phpdoc-md generate --visibility=public --methodRegex='/^get/' --see includes/src > api.md
```

##### Bootstrapping

If you are not using the composer autoloader, or if there is something else that needs to be done before your classes can be instantiated, then you may request
phpdoc-md to load a php bootstrap file prior to generating the docs

```shell
./vendor/bin/phpdoc-md generate --bootstrap=includes/init.php includes/src > api.md
```

##### Excluding directories

You can tell the command line tool to ignore certain directories in your class path by using the `--ignore` option.

```shell
./phpdoc-md generate --ignore=test,examples includes/src > api.md
```
