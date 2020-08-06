<?php
namespace Psalm\Tests\Template;

use Psalm\Tests\TestCase;
use Psalm\Tests\Traits;

class FunctionClassStringTemplateTest extends TestCase
{
    use Traits\InvalidCodeAnalysisTestTrait;
    use Traits\ValidCodeAnalysisTestTrait;

    /**
     * @return iterable<string,array{string,assertions?:array<string,string>,error_levels?:string[]}>
     */
    public function providerValidCodeParse()
    {
        return [
            'callStaticMethodOnTemplatedClassName' => [
                '<?php
                    /**
                     * @template T
                     * @param class-string $class
                     * @template-typeof T $class
                     */
                    function foo(string $class, array $args) : void {
                        $class::bar($args);
                    }',
                'assertions' => [],
                'error_levels' => ['MixedMethodCall'],
            ],
            'returnTemplatedClassClassName' => [
                '<?php
                    class I {
                        /**
                         * @template T as Foo
                         * @param class-string<T> $class
                         * @return T|null
                         */
                        public function loader(string $class) {
                            return $class::load();
                        }
                    }

                    /**
                     * @psalm-consistent-constructor
                     */
                    class Foo {
                        /** @return static */
                        public static function load() {
                            return new static();
                        }
                    }

                    class FooChild extends Foo{}

                    $a = (new I)->loader(FooChild::class);',
                'assertions' => [
                    '$a' => 'FooChild|null',
                ],
            ],
            'upcastIterableToTraversable' => [
                '<?php
                    /**
                     * @template T as iterable
                     * @param T::class $class
                     */
                    function foo(string $class) : void {
                        $a = new $class();

                        foreach ($a as $b) {}
                    }',
                'assertions' => [],
                'error_levels' => ['MixedAssignment'],
            ],
            'upcastGenericIterableToGenericTraversable' => [
                '<?php
                    /**
                     * @template T as iterable<int>
                     * @param T::class $class
                     */
                    function foo(string $class) : void {
                        $a = new $class();

                        foreach ($a as $b) {}
                    }',
                'assertions' => [],
                'error_levels' => [],
            ],
            'understandTemplatedCalculationInOtherFunction' => [
                '<?php
                    /**
                     * @template T as Exception
                     * @param T::class $type
                     * @return T
                     * @psalm-suppress UnsafeInstantiation
                     */
                    function a(string $type): Exception {
                        return new $type;
                    }

                    /**
                     * @template T as InvalidArgumentException
                     * @param T::class $type
                     * @return T
                     */
                    function b(string $type): InvalidArgumentException {
                        return a($type);
                    }',
            ],
            'objectReturn' => [
                '<?php
                    /**
                     * @template T as object
                     *
                     * @param class-string<T> $foo
                     *
                     * @return T
                     *
                     * @psalm-suppress MixedMethodCall
                     */
                    function Foo(string $foo) : object {
                      return new $foo;
                    }

                    echo Foo(DateTime::class)->format("c");',
            ],
            'templatedClassStringParamAsClass' => [
                '<?php
                    /**
                     * @psalm-consistent-constructor
                     */
                    abstract class C {
                        public function foo() : void{}
                    }

                    class E {
                        /**
                         * @template T as C
                         * @param class-string<T> $c_class
                         *
                         * @return C
                         * @psalm-return T
                         */
                        public static function get(string $c_class) : C {
                            $c = new $c_class;
                            $c->foo();
                            return $c;
                        }
                    }

                    /**
                     * @param class-string<C> $c_class
                     */
                    function bar(string $c_class) : void {
                        $c = E::get($c_class);
                        $c->foo();
                    }

                    /**
                     * @psalm-suppress TypeCoercion
                     */
                    function bat(string $c_class) : void {
                        $c = E::get($c_class);
                        $c->foo();
                    }',
            ],
            'templatedClassStringParamAsObject' => [
                '<?php
                    /**
                     * @psalm-consistent-constructor
                     */
                    abstract class C {
                        public function foo() : void{}
                    }

                    class E {
                        /**
                         * @template T as object
                         * @param class-string<T> $c_class
                         *
                         * @psalm-return T
                         * @psalm-suppress MixedMethodCall
                         */
                        public static function get(string $c_class) {
                            return new $c_class;
                        }
                    }

                    /**
                     * @psalm-suppress TypeCoercion
                     */
                    function bat(string $c_class) : void {
                        $c = E::get($c_class);
                        $c->bar = "bax";
                    }',
            ],
            'templatedClassStringParamMoreSpecific' => [
                '<?php
                    /**
                     * @psalm-consistent-constructor
                     */
                    abstract class C {
                        public function foo() : void{}
                    }

                    class D extends C {
                        public function faa() : void{}
                    }

                    class E {
                        /**
                         * @template T as C
                         * @param class-string<T> $c_class
                         *
                         * @return C
                         * @psalm-return T
                         */
                        public static function get(string $c_class) : C {
                            $c = new $c_class;
                            $c->foo();
                            return $c;
                        }
                    }

                    /**
                     * @param class-string<D> $d_class
                     */
                    function moreSpecific(string $d_class) : void {
                        $d = E::get($d_class);
                        $d->foo();
                        $d->faa();
                    }',
            ],
            'templateFilterArrayWithIntersection' => [
                '<?php
                    /**
                     * @template T as object
                     * @template S as object
                     * @param array<T> $a
                     * @param class-string<S> $type
                     * @return array<T&S>
                     */
                    function filter(array $a, string $type): array {
                        $result = [];
                        foreach ($a as $item) {
                            if (is_a($item, $type)) {
                                $result[] = $item;
                            }
                        }
                        return $result;
                    }

                    interface A {}
                    interface B {}

                    /** @var array<A> */
                    $x = [];
                    $y = filter($x, B::class);',
                [
                    '$y' => 'array<array-key, A&B>',
                ],
            ],
            'templateFilterWithIntersection' => [
                '<?php
                    /**
                     * @template T as object
                     * @template S as object
                     * @param T $item
                     * @param class-string<S> $type
                     * @return T&S
                     */
                    function filter($item, string $type) {
                        if (is_a($item, $type)) {
                            return $item;
                        };

                        throw new \UnexpectedValueException("bad");
                    }

                    interface A {}
                    interface B {}

                    /** @var A */
                    $x = null;

                    $y = filter($x, B::class);',
                [
                    '$y' => 'A&B',
                ],
            ],
            'unionTOrClassStringTPassedClassString' => [
                '<?php
                    /**
                     * @psalm-template T of object
                     * @psalm-param T|class-string<T> $someType
                     * @psalm-return T
                     * @psalm-suppress MixedMethodCall
                     */
                    function getObject($someType) {
                        if (is_object($someType)) {
                            return $someType;
                        }

                        return new $someType();
                    }

                    class C {
                        function sayHello() : string {
                            return "hi";
                        }
                    }

                    getObject(C::class)->sayHello();',
            ],
            'unionTOrClassStringTPassedObject' => [
                '<?php
                    /**
                     * @psalm-template T of object
                     * @psalm-param T|class-string<T> $someType
                     * @psalm-return T
                     * @psalm-suppress MixedMethodCall
                     */
                    function getObject($someType) {
                        if (is_object($someType)) {
                            return $someType;
                        }

                        return new $someType();
                    }

                    class C {
                        function sayHello() : string {
                            return "hi";
                        }
                    }

                    getObject(new C())->sayHello();',
            ],
            'dontModifyByRefTemplatedArray' => [
                '<?php
                    class A {}
                    class B {}

                    /**
                     * @template T of object
                     * @param class-string<T> $className
                     * @param array<T> $map
                     * @param-out array<T> $map
                     * @param int $id
                     * @return T
                     * @psalm-suppress MixedMethodCall
                     */
                    function get(string $className, array &$map, int $id) {
                        if(!array_key_exists($id, $map)) {
                            $map[$id] = new $className();
                        }
                        return $map[$id];
                    }

                    /**
                     * @param array<A> $mapA
                     */
                    function getA(int $id, array $mapA): A {
                        return get(A::class, $mapA, $id);
                    }

                    /**
                     * @param array<B> $mapB
                     */
                    function getB(int $id, array $mapB): B {
                        return get(B::class, $mapB, $id);
                    }',
            ],
            'unionClassStringTWithTReturnsObjectWhenCoerced' => [
                '<?php
                    /**
                     * @template T as object
                     * @param T|class-string<T> $s
                     * @return T
                     * @psalm-suppress MixedMethodCall
                     */
                    function bar($s) {
                        if (is_object($s)) {
                            return $s;
                        }

                        return new $s();
                    }

                    function foo(string $s) : object {
                        /** @psalm-suppress ArgumentTypeCoercion */
                        return bar($s);
                    }',
            ],

            'allowTemplatedIntersectionFirst' => [
                '<?php
                    class MockObject
                    {
                        public function checkExpectations() : void
                        {
                        }
                    }

                    /**
                     * @psalm-template RequestedType
                     * @psalm-param class-string<RequestedType> $className
                     * @psalm-return RequestedType&MockObject
                     * @psalm-suppress MixedInferredReturnType
                     * @psalm-suppress MixedReturnStatement
                     */
                    function mock(string $className)
                    {
                        eval(\'"there be dragons"\');

                        return $instance;
                    }

                    class A {
                        public function foo() : void {}
                    }

                    /**
                     * @psalm-template UnknownType
                     * @psalm-param class-string<UnknownType> $className
                     */
                    function useMockTemplated(string $className) : void
                    {
                        mock($className)->checkExpectations();
                    }

                    mock(A::class)->foo();',
            ],
            'allowTemplatedIntersectionFirstTemplatedMock' => [
                '<?php
                    class MockObject
                    {
                        public function checkExpectations() : void
                        {
                        }
                    }

                    /**
                     * @psalm-template RequestedType
                     * @psalm-param class-string<RequestedType> $className
                     * @psalm-return RequestedType&MockObject
                     * @psalm-suppress MixedInferredReturnType
                     * @psalm-suppress MixedReturnStatement
                     */
                    function mock(string $className)
                    {
                        eval(\'"there be dragons"\');

                        return $instance;
                    }

                    class A {
                        public function foo() : void {}
                    }

                    /**
                     * @psalm-template UnknownType
                     * @psalm-param class-string<UnknownType> $className
                     */
                    function useMockTemplated(string $className) : void
                    {
                        mock($className)->checkExpectations();
                    }

                    mock(A::class)->foo();',
            ],
            'allowTemplatedIntersectionSecond' => [
                '<?php
                    class MockObject
                    {
                        public function checkExpectations() : void
                        {
                        }
                    }

                    /**
                     * @psalm-template RequestedType
                     * @psalm-param class-string<RequestedType> $className
                     * @psalm-return MockObject&RequestedType
                     * @psalm-suppress MixedInferredReturnType
                     * @psalm-suppress MixedReturnStatement
                     */
                    function mock(string $className)
                    {
                        eval(\'"there be dragons"\');

                        return $instance;
                    }

                    class A {
                        public function foo() : void {}
                    }

                    /**
                     * @psalm-param class-string $className
                     */
                    function useMock(string $className) : void {
                        mock($className)->checkExpectations();
                    }

                    /**
                     * @psalm-template UnknownType
                     * @psalm-param class-string<UnknownType> $className
                     */
                    function useMockTemplated(string $className) : void
                    {
                        mock($className)->checkExpectations();
                    }

                    mock(A::class)->foo();',
            ],
            'returnClassString' => [
                '<?php
                    /**
                     * @template T
                     * @param T::class $s
                     * @return T::class
                     */
                    function foo(string $s) : string {
                        return $s;
                    }

                    /**
                     * @param  A::class $s
                     */
                    function bar(string $s) : void {
                    }

                    class A {}

                    bar(foo(A::class));',
            ],
            'templateAsUnionClassStringPassingValidClass' => [
                '<?php
                    /**
                     * @psalm-consistent-constructor
                     */
                    class A {}

                    /**
                     * @psalm-consistent-constructor
                     */
                    class B {}

                    /**
                     * @template T1 as A
                     * @template T2 as B
                     * @param class-string<T1>|class-string<T2> $type
                     * @return T1|T2
                     */
                    function f(string $type) {
                        return new $type();
                    }

                    f(A::class);
                    f(B::class);',
            ],
            'SKIPPED-compareToExactClassString' => [
                '<?php
                    /**
                     * @template T as object
                     */
                    class Type
                    {
                        /** @var class-string<T> */
                        private $typeName;

                        /**
                         * @param class-string<T> $typeName
                         */
                        public function __construct(string $typeName) {
                            $this->typeName = $typeName;
                        }

                        /**
                         * @param mixed $value
                         * @return T
                         */
                        public function cast($value) {
                            if (is_object($value) && get_class($value) === $this->typeName) {
                                return $value;
                            }
                            throw new RuntimeException();
                        }
                    }',
            ],
            'compareGetClassTypeString' => [
                '<?php
                    /**
                     * @template T
                     * @param class-string<T> $typeName
                     * @param mixed $value
                     * @return T
                     */
                    function cast($value, string $typeName) {
                        if (is_object($value) && get_class($value) === $typeName) {
                            return $value;
                        }

                        throw new RuntimeException();
                    }',
            ],
            'instanceofTemplatedClassStringOnMixed' => [
                '<?php
                    interface Foo {}

                    /**
                     * @template T as Foo
                     * @param class-string<T> $fooClass
                     * @param mixed $foo
                     * @return T
                     */
                    function get($fooClass, $foo) {
                        if ($foo instanceof $fooClass) {
                            return $foo;
                        }

                        throw new \Exception();
                    }',
            ],
            'instanceofTemplatedClassStringOnObjectType' => [
                '<?php
                    interface Foo {}

                    /**
                     * @template T as Foo
                     * @param class-string<T> $fooClass
                     * @return T
                     */
                    function get($fooClass, Foo $foo) {
                        if ($foo instanceof $fooClass) {
                            return $foo;
                        }

                        throw new \Exception();
                    }',
            ],
            'templateFromDifferentClassStrings' => [
                '<?php
                    /**
                     * @psalm-consistent-constructor
                     */
                    class A {}

                    class B extends A {}
                    class C extends A {}

                    /**
                     * @template T of A
                     * @param class-string<T> $a1
                     * @param class-string<T> $a2
                     * @return T
                     */
                    function test(string $a1, string $a2) {
                        if (rand(0, 1)) return new $a1();

                        return new $a2();
                    }

                    $b_or_c = test(B::class, C::class);',
                [
                    '$b_or_c' => 'B|C',
                ]
            ],
            'allowComparisonWithoutCrash' => [
                '<?php
                    /**
                     * @template T as object
                     *
                     * @param T::class $e
                     * @param T::class $expected
                     */
                    function bar(string $e, string $expected) : void {
                        if ($e !== $expected) {}
                    }',
            ],
            'refineByArrayFilterIntersection' => [
                '<?php
                    /**
                     * @template T
                     * @param array<Bar> $bars
                     * @psalm-param class-string<T> $class
                     * @return array<T&Bar>
                     */
                    function getBarsThatAreInstancesOf(array $bars, string $class): array
                    {
                        return \array_filter(
                            $bars,
                            function (Bar $bar) use ($class): bool {
                                return $bar instanceof $class;
                            }
                        );
                    }

                    interface Bar {}'
            ],
        ];
    }

    /**
     * @return iterable<string,array{string,error_message:string,2?:string[],3?:bool,4?:string}>
     */
    public function providerInvalidCodeParse()
    {
        return [
            'copyScopedClassInFunction' => [
                '<?php
                    /**
                     * @template Throwable as DOMNode
                     *
                     * @param class-string<Throwable> $foo
                     */
                    function Foo(string $foo) : string {
                        return $foo;
                    }',
                'error_message' => 'ReservedWord',
            ],
            'copyScopedClassInNamespacedFunction' => [
                '<?php
                    namespace Foo;

                    class Bar {}

                    /**
                     * @template Bar as DOMNode
                     *
                     * @param class-string<Bar> $foo
                     */
                    function Foo(string $foo) : string {
                        return $foo;
                    }',
                'error_message' => 'ReservedWord',
            ],
            'constrainTemplateTypeWhenClassStringUsed' => [
                '<?php
                    class GenericObjectFactory {
                       /**
                        * @psalm-template T
                        * @psalm-param class-string<T> $type
                        * @psalm-return T
                        */
                        public function getObject(string $type)
                        {
                            return 3;
                        }
                    }',
                'error_message' => 'InvalidReturnStatement',
            ],
            'forbidLossOfInformationWhenCoercing' => [
                '<?php
                    /**
                     * @template T as iterable<int>
                     * @param T::class $class
                     */
                    function foo(string $class) : void {}

                    function bar(Traversable $t) : void {
                        foo(get_class($t));
                    }',
                'error_message' => 'MixedArgumentTypeCoercion',
            ],
            'templateAsUnionClassStringPassingInvalidClass' => [
                '<?php
                    /**
                     * @psalm-consistent-constructor
                     */
                    class A {}

                    /**
                     * @psalm-consistent-constructor
                     */
                    class B {}

                    class C {}

                    /**
                     * @template T1 as A
                     * @template T2 as B
                     * @param class-string<T1>|class-string<T2> $type
                     * @return T1|T2
                     */
                    function f(string $type) {
                        return new $type();
                    }

                    f(C::class);',
                'error_message' => 'InvalidArgument',
            ],
        ];
    }
}
