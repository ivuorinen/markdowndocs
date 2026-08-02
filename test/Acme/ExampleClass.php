<?php

namespace Acme;

/**
 * Interface ExampleInterface
 *
 * @package Acme
 * @ignore
 */
interface ExampleInterface
{

    /**
     * @param string $arg
     *
     * @return \stdClass
     */
    public function func($arg = 'a');
}

interface InterfaceReferringToImportedClass
{

    /**
     * @return CLI
     */
    public function theFunc();

    /**
     * @return CLI[]
     */
    public function funcReturningArr();
}

/**
 * This is a description
 * of this class
 *
 * @package Acme
 */
abstract class ExampleClass implements \Reflector
{

    /**
     * Description of a*a
     *
     * @param       $arg
     * @param array $arr
     * @param int   $bool
     */
    public function funcA($arg, array $arr, $bool = 10)
    {
    }

    /**
     * Description of b
     *
     * @param int   $arg
     * @param array $arr
     * @param int   $bool
     *
     * @example
     *      <code>
     *      <?php
     *      $lorem = 'te';
     *      $ipsum = 'dolor';
     *      </code>
     *
     */
    public function funcB($arg, array $arr, $bool = 10)
    {
    }

    public function funcD($arg, $arr = [], ?ExampleInterface $depr = null, ?\stdClass $class = null)
    {
    }

    public function getFunc()
    {
    }

    public function hasFunc()
    {
    }

    abstract public function isFunc();

    /**
     * @ignore
     */
    public function someFunc()
    {
    }

    /**
     * Description of c
     *
     * @param       $arg
     * @param array $arr
     * @param int   $bool
     *
     * @return \Acme\ExampleClass
     * @deprecated This one is deprecated
     */
    protected function funcC($arg, array $arr, $bool = 10)
    {
    }

    private function privFunc()
    {
    }
}

/**
 * @deprecated This one is deprecated
 *
 * Lorem te ipsum
 *
 * @package    Acme
 */
class ExampleClassDepr
{
}

class SomeClass
{

    /**
     * @return int
     */
    public function aMethod()
    {
    }
}

class ClassImplementingInterface extends SomeClass implements ExampleInterface
{
    /**
     * @inheritdoc
     */
    public function func($arg = 'a')
    {
    }

    /**
     * @inheritDoc
     */
    public function aMethod()
    {
    }

    /**
     * @return \FilesystemIterator
     */
    public function methodReturnNativeClass()
    {
    }

    /**
     * @return \FilesystemIterator[]
     */
    public function methodReturningArrayNativeClass()
    {
    }
}

use PHPDocsMD\Console\CLI;

class ClassWithStaticFunc
{

    /**
     * @return float
     */
    public static function someStaticFunc()
    {
    }
}

class ClassWithDocumentedParams
{

    /**
     * @param int    $count  How many things
     * @param string $label  Naming
     * @param int    $offset Where to start
     */
    public function documented($count, $label, $offset)
    {
    }
}

class ClassWithFalsyDefaults
{
    public function falsy($int = 0, $float = 0.0, $bool = false, $none = null)
    {
    }

    /**
     * A documented type alongside a 0 default: the branch that merges the two
     * must not key off the formatted default, which is the falsy string "0".
     *
     * @param string $documented
     */
    public function falsyWithDocumentedType($documented = 0)
    {
    }
}

class ClassWithFluentInterface
{

    /**
     * @return self
     */
    public function fluent()
    {
    }
}
