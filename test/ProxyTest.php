<?php

namespace Amp\Injector;

use PHPUnit\Framework\TestCase;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\Proxy\LazyLoadingInterface;

class ProxyTest extends TestCase
{
    public function testInstanceReturnedFromProxy(): void
    {
        $injector = new Injector;
        $injector->proxy(
            TestDependency::class,
            static function (string $className, callable $callback) {
                return (new LazyLoadingValueHolderFactory)->createProxy(
                    $className,
                    static function (&$object, $proxy, $method, $parameters, &$initializer) use ($callback) {
                        $object = $callback();
                        $initializer = null;
                    }
                );
            }
        );

        $class = $injector->make(TestDependency::class);

        self::assertInstanceOf(TestDependency::class, $class);
        self::assertInstanceOf(LazyLoadingInterface::class, $class);
        self::assertEquals('testVal', $class->testProp);
    }

    public function testMakeInstanceInjectsSimpleConcreteDependencyProxy(): void
    {
        $injector = new Injector;
        $injector->proxy(
            TestDependency::class,
            static function (string $className, callable $callback) {
                return (new LazyLoadingValueHolderFactory)->createProxy(
                    $className,
                    static function (&$object, $proxy, $method, $parameters, &$initializer) use ($callback) {
                        $object = $callback();
                        $initializer = null;
                    }
                );
            }
        );

        $needDep = $injector->make(TestNeedsDep::class);

        self::assertInstanceOf(TestNeedsDep::class, $needDep, '');
    }

    public function testShareInstanceProxy(): void
    {
        $injector = new Injector;
        $injector->proxy(
            TestDependency::class,
            static function (string $className, callable $callback) {
                return (new LazyLoadingValueHolderFactory)->createProxy(
                    $className,
                    static function (&$object, $proxy, $method, $parameters, &$initializer) use ($callback) {
                        $object = $callback();
                        $initializer = null;
                    }
                );
            }
        );

        $injector->share(TestDependency::class);
        $object1 = $injector->make(TestDependency::class);
        $object2 = $injector->make(TestDependency::class);

        self::assertSame($object1, $object2);
    }

    public function testProxyMakeInstanceReturnsAliasInstanceOnNonConcreteTypehint(): void
    {
        $injector = new Injector;
        $injector->alias(DepInterface::class, DepImplementation::class);
        $injector->proxy(
            DepInterface::class,
            static function (string $className, callable $callback) {
                return (new LazyLoadingValueHolderFactory)->createProxy(
                    $className,
                    static function (&$object, $proxy, $method, $parameters, &$initializer) use ($callback) {
                        $object = $callback();
                        $initializer = null;
                    }
                );
            }
        );

        $object = $injector->make(DepInterface::class);

        self::assertInstanceOf(DepInterface::class, $object);
        self::assertInstanceOf(DepImplementation::class, $object);
        self::assertInstanceOf(LazyLoadingInterface::class, $object);
    }

    public function testProxyDefinition(): void
    {
        $injector = new Injector;
        $injector->proxy(
            NoTypehintNoDefaultConstructorClass::class,
            static function (string $className, callable $callback) {
                return (new LazyLoadingValueHolderFactory)->createProxy(
                    $className,
                    static function (&$object, $proxy, $method, $parameters, &$initializer) use ($callback) {
                        $object = $callback();
                        $initializer = null;
                    }
                );
            }
        );

        $injector->define(NoTypehintNoDefaultConstructorClass::class, [
            ':arg' => 42,
        ]);

        $obj = $injector->make(NoTypehintNoDefaultConstructorClass::class);

        self::assertEquals(42, $obj->testParam);
    }

    public function testProxyInjectionDefinition(): void
    {
        $injector = new Injector;
        $injector->proxy(
            NoTypehintNoDefaultConstructorClass::class,
            static function (string $className, callable $callback) {
                return (new LazyLoadingValueHolderFactory)->createProxy(
                    $className,
                    static function (&$object, $proxy, $method, $parameters, &$initializer) use ($callback) {
                        $object = $callback();
                        $initializer = null;
                    }
                );
            }
        );

        $obj = $injector->make(NoTypehintNoDefaultConstructorClass::class, [
            ':arg' => 42,
        ]);

        self::assertEquals(42, $obj->testParam);
    }

    public function testProxyParamDefinition(): void
    {
        $injector = new Injector;
        $injector->proxy(
            NoTypehintNoDefaultConstructorClass::class,
            static function (string $className, callable $callback) {
                return (new LazyLoadingValueHolderFactory)->createProxy(
                    $className,
                    static function (&$object, $proxy, $method, $parameters, &$initializer) use ($callback) {
                        $object = $callback();
                        $initializer = null;
                    }
                );
            }
        );

        $injector->defineParam('arg', 42);

        $obj = $injector->make(NoTypehintNoDefaultConstructorClass::class);

        self::assertSame(42, $obj->testParam);
    }

    public function testProxyPrepare(): void
    {
        $injector = new Injector();
        $injector->proxy(
            PreparesImplementationTest::class,
            static function (string $className, callable $callback) {
                return (new LazyLoadingValueHolderFactory)->createProxy(
                    $className,
                    static function (&$object, $proxy, $method, $parameters, &$initializer) use ($callback) {
                        $object = $callback();
                        $initializer = null;
                    }
                );
            }
        );

        $injector->prepare(
            PreparesImplementationTest::class,
            function (PreparesImplementationTest $obj, $injector) {
                $obj->testProp = 42;
            }
        );

        $obj = $injector->make(PreparesImplementationTest::class);

        self::assertSame(42, $obj->testProp);
    }

    public function testProxyAssertDelegateOverrideProxy(): void
    {
        $injector = new Injector();
        $injector->proxy(PreparesImplementationTest::class, function () {
        });
        $injector->delegate(
            PreparesImplementationTest::class,
            function () {
                return new PreparesImplementationTest();
            }
        );

        $object = $injector->make(PreparesImplementationTest::class);

        self::assertInstanceOf(PreparesImplementationTest::class, $object);
        self::assertNotInstanceOf(LazyLoadingInterface::class, $object);
    }
}
