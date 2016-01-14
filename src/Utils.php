<?php

/**
 * @author     Ignas Rudaitis <ignas.rudaitis@gmail.com>
 * @copyright  2010-2016 Ignas Rudaitis
 * @license    http://www.opensource.org/licenses/mit-license.html
 * @link       http://antecedent.github.com/patchwork
 */
namespace Patchwork\Utils;

const ALIASING_CODE = '
    namespace %s;
    function %s() {
        return call_user_func_array("%s", func_get_args());
    }
';

function clearOpcodeCaches()
{
    if (ini_get('wincache.ocenabled')) {
        wincache_refresh_if_changed();
    }
    if (ini_get('apc.enabled')) {
        apc_clear_cache();
    }
}

function generatorsSupported()
{
    return version_compare(PHP_VERSION, "5.5", ">=");
}

function runningOnHHVM()
{
    return defined("HHVM_VERSION");
}

function condense($string)
{
    return preg_replace('/\s*/', '', $string);
}

function findFirstGreaterThan(array $array, $value)
{
    $low = 0;
    $high = count($array) - 1;
    if ($array[$high] <= $value) {
        return $high + 1;
    }
    while ($low < $high) {
        $mid = (int)(($low + $high) / 2);
        if ($array[$mid] <= $value) {
            $low = $mid + 1;
        } else {
            $high = $mid;
        }
    }
    return $low;
}

function interpretCallable($callback)
{
    if (is_object($callback)) {
        return interpretCallable([$callback, "__invoke"]);
    }
    if (is_array($callback)) {
        list($class, $method) = $callback;
        $instance = null;
        if (is_object($class)) {
            $instance = $class;
            $class = get_class($class);
        }
        $class = ltrim($class, "\\");
        return [$class, $method, $instance];
    }
    $callback = ltrim($callback, "\\");
    if (strpos($callback, "::")) {
        list($class, $method) = explode("::", $callback);
        return [$class, $method, null];
    }
    return [null, $callback, null];
}

function callableDefined($callable, $shouldAutoload = false)
{
    list($class, $method, $instance) = interpretCallable($callable);
    if ($instance !== null) {
        return true;
    }
    if (isset($class)) {
        return classOrTraitExists($class, $shouldAutoload) &&
               method_exists($class, $method);
    }
    return function_exists($method);
}

function classOrTraitExists($classOrTrait, $shouldAutoload = true)
{
    return class_exists($classOrTrait, $shouldAutoload)
        || trait_exists($classOrTrait, $shouldAutoload);
}

function append(&$array, $value)
{
    $array[] = $value;
    end($array);
    return key($array);
}

function normalizePath($path)
{
    return rtrim(strtr($path, "\\", "/"), "/");
}

function reflectCallable($callback)
{
    if ($callback instanceof \Closure) {
        return new \ReflectionFunction($callback);
    }
    list($class, $method) = interpretCallable($callback);
    if (isset($class)) {
        return new \ReflectionMethod($class, $method);
    }
    return new \ReflectionFunction($method);
}

function callableToString($callback)
{
    list($class, $method) = interpretCallable($callback);
    if (isset($class)) {
        return $class . "::" . $method;
    }
    return $method;
}

function alias($namespace, array $mapping)
{
    foreach ($mapping as $original => $aliases) {
        $original = ltrim(str_replace('\\', '\\\\', $namespace) . '\\\\' . $original, '\\');
        foreach ((array) $aliases as $alias) {
            eval(sprintf(ALIASING_CODE, $namespace, $alias, $original));
        }
    }
}

function getUserDefinedCallables()
{
    return array_merge(get_defined_functions()['user'], getUserDefinedMethods());
}

function getUserDefinedMethods()
{
    static $result = [];
    static $classCount = 0;
    $classes = getUserDefinedClassesAndTraits();
    $newClasses = array_slice($classes, $classCount);
    foreach ($newClasses as $newClass) {
        foreach (get_class_methods($newClass) as $method) {
            $result[] = $newClass . '::' . $method;
        }
    }
    $classCount = count($classes);
    return $result;
}

function getUserDefinedClassesAndTraits()
{
    static $classCutoff;
    static $traitCutoff;
    $classes = get_declared_classes();
    $traits = get_declared_traits();
    if (!isset($classCutoff)) {
        $classCutoff = count($classes);
        for ($i = 0; $i < count($classes); $i++) {
            if ((new \ReflectionClass($classes[$i]))->isUserDefined()) {
                $classCutoff = $i;
                break;
            }
        }
    }
    if (!isset($traitCutoff)) {
        $traitCutoff = count($traits);
        for ($i = 0; $i < count($traits); $i++) {
            $methods = get_class_methods($traits[$i]);
            if (empty($methods)) {
                continue;
            }
            list($first) = $methods;
            if ((new \ReflectionMethod($traits[$i], $first))->isUserDefined()) {
                $traitCutoff = $i;
                break;
            }
        }
    }
    return array_merge(array_slice($classes, $classCutoff),
                       array_slice($traits, $traitCutoff));
}

function matchWildcard($wildcard, array $subjects)
{
    $table = ['*' => '.*', '{' => '(', '}' => ')', ' ' => ''];
    $pattern = '/' . strtr($wildcard, $table) . '/';
    return preg_grep($pattern, $subjects);
}

function isOwnName($name)
{
    return stripos((string) $name, 'Patchwork\\') === 0;
}

function isForeignName($name)
{
    return !isOwnName($name);
}
