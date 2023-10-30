<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

/**
 * Class TestHelper provides methods for easy substitution/replacement of private and protected methods and properties
 * @package Tests
 */
class TestHelper extends TestCase
{
    /**
     * Gets the object that reports information about a class.
     * When using Reflection on mocked classes, properties with original names can only be found on parent class
     *
     * @param mixed $class Either a string containing the name of the class to reflect, or an object.
     *
     * @return ReflectionClass  instance of the object used for inspection of the passed class
     *
     * @throws ReflectionException if the class does not exist.
     */
    public static function getReflectedClass($class)
    {
        // When using Reflection on mocked classes, properties with original names can only be found on parent class
        if (is_subclass_of($class, 'PHPUnit_Framework_MockObject_MockObject')) {
            return new ReflectionClass(get_parent_class($class));
        }

        return new ReflectionClass($class);
    }

    /**
     * Convenience method to call a private or protected function
     *
     * @param object|string     $objectOrClassName  The object or class name on which the function will be called.
     *                                              If the function is static,
     *                                              then this should be a string of the class name.
     * @param string            $functionName       The name of the function to be invoked
     * @param array             $arguments          An indexed array of arguments to be passed to the function in the
     *                                              order that they are declared
     *
     * @return mixed    the value returned by the function
     *
     * @throws ReflectionException   if a specified object, class or method does not exist.
     */
    public static function callNonPublicFunction($objectOrClassName, string $functionName, $arguments = [])
    {
        try {
            $reflectedMethod = self::getReflectedClass($objectOrClassName)->getMethod($functionName);
            $reflectedMethod->setAccessible(true);

            return $reflectedMethod->invokeArgs(is_object($objectOrClassName) ? $objectOrClassName : null, $arguments);
        } finally {
            if ($reflectedMethod && ($reflectedMethod->isProtected() || $reflectedMethod->isPrivate())) {
                $reflectedMethod->setAccessible(false);
            }
        }
    }

    /**
     * Convenience method to set a private or protected property
     *
     * @param object|string $objectOrClassName  The object of the property to be set
     *                                          If the property is static,
     *                                          then a this should be a string of the class name.
     * @param string        $propertyName       The name of the property to be set
     * @param mixed         $value              The value to be set to the named property
     *
     * @throws ReflectionException  if a specified object, class or property does not exist.
     */
    public static function setNonPublicProperty($objectOrClassName, string $propertyName, $value)
    {
        try {
            $reflectedProperty = self::getReflectedClass($objectOrClassName)->getProperty($propertyName);
            $reflectedProperty->setAccessible(true);

            if (is_object($objectOrClassName)) {
                $reflectedProperty->setValue($objectOrClassName, $value);
            } else {
                $reflectedProperty->setValue($value);
            }
        } finally {
            if ($reflectedProperty && ($reflectedProperty->isProtected() || $reflectedProperty->isPrivate())) {
                $reflectedProperty->setAccessible(false);
            }
        }
    }

    /**
     * Convenience method to get a private or protected property
     *
     * @param object|string $objectOrClassName  The object of the property to be retrieved
     *                                          If the property is static,
     *                                          then a this should be a string of the class name.
     * @param string        $propertyName       The name of the property to be retrieved
     *
     * @return mixed    The value of the property
     *
     * @throws ReflectionException  if a specified object, class or property does not exist.
     */
    public static function getNonPublicProperty($objectOrClassName, string $propertyName)
    {
        try {
            $reflectedProperty = self::getReflectedClass($objectOrClassName)->getProperty($propertyName);
            $reflectedProperty->setAccessible(true);

            return $reflectedProperty->getValue(is_object($objectOrClassName) ? $objectOrClassName : null);

        } finally {
            if ($reflectedProperty && ($reflectedProperty->isProtected() || $reflectedProperty->isPrivate())) {
                $reflectedProperty->setAccessible(false);
            }
        }
    }
}
