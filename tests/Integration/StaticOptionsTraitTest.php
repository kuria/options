<?php declare(strict_types=1);

namespace Kuria\Options\Integration;

use Kuria\DevMeta\Test;
use Kuria\Options\Integration\TestSubject\Configurable;
use Kuria\Options\Integration\TestSubject\ConfigurableChild;

class StaticOptionsTraitTest extends Test
{
    function testShouldDefineOptionsOncePerClass()
    {
        Configurable::reset();

        $this->assertSame(0, Configurable::$defineOptionsCallCount); // initial state

        $this->assertSame(
            ['name' => 'Foo', 'score' => 0],
            Configurable::resolveOptions(['name' => 'Foo'])->toArray()
        );

        $this->assertSame(1, Configurable::$defineOptionsCallCount); // initial call
        $this->assertSame(['Foo'], Configurable::$lastValidatorArgs);

        $this->assertSame(
            ['name' => 'Bar', 'score' => 123],
            Configurable::resolveOptions(['name' => 'Bar', 'score' => 123])->toArray()
        );

        $this->assertSame(1, Configurable::$defineOptionsCallCount); // should be cached
        $this->assertSame(['Bar'], Configurable::$lastValidatorArgs);

        $this->assertSame(0, ConfigurableChild::$defineOptionsCallCount); // initial state

        $this->assertSame(
            ['name' => 'Foo', 'score' => 0, 'level' => 1],
            ConfigurableChild::resolveOptions(['name' => 'Foo'])->toArray()
        );

        $this->assertSame(1, ConfigurableChild::$defineOptionsCallCount); // initial call
        $this->assertSame(['Foo'], Configurable::$lastValidatorArgs);

        $this->assertSame(
            ['name' => 'Bar', 'score' => 123, 'level' => 5],
            ConfigurableChild::resolveOptions(['name' => 'Bar', 'score' => 123, 'level' => 5])->toArray()
        );

        $this->assertSame(1, ConfigurableChild::$defineOptionsCallCount); // should be cached
        $this->assertSame(['Bar'], Configurable::$lastValidatorArgs);
    }

    /**
     * @depends testShouldDefineOptionsOncePerClass
     */
    function testShouldClearConfigResolverCache()
    {
        $this->assertSame(1, Configurable::$defineOptionsCallCount);
        $this->assertSame(1, ConfigurableChild::$defineOptionsCallCount);

        Configurable::clearOptionsResolverCache(); // should clear child caches as well

        $this->assertSame(
            ['name' => 'Baz', 'score' => 456],
            Configurable::resolveOptions(['name' => 'Baz', 'score' => 456])->toArray()
        );

        $this->assertSame(
            ['name' => 'Baz', 'score' => 456, 'level' => 2],
            ConfigurableChild::resolveOptions(['name' => 'Baz', 'score' => 456, 'level' => 2])->toArray()
        );

        $this->assertSame(2, Configurable::$defineOptionsCallCount);
        $this->assertSame(2, ConfigurableChild::$defineOptionsCallCount);

        $this->assertSame(
            ['name' => 'Qux', 'score' => 0],
            Configurable::resolveOptions(['name' => 'Qux'])->toArray()
        );

        $this->assertSame(
            ['name' => 'Qux', 'score' => 0, 'level' => 1],
            ConfigurableChild::resolveOptions(['name' => 'Qux'])->toArray()
        );

        $this->assertSame(2, Configurable::$defineOptionsCallCount); // should be cached again
        $this->assertSame(2, ConfigurableChild::$defineOptionsCallCount); // should be cached again
    }

    /**
     * @depends testShouldClearConfigResolverCache
     */
    function testShouldResolveOptionsWithContext()
    {
        $this->assertSame(
            ['name' => 'Quux', 'score' => 0],
            Configurable::resolveOptions(['name' => 'Quux'], ['foo', 123])->toArray()
        );

        $this->assertSame(['Quux', 'foo', 123], Configurable::$lastValidatorArgs);
    }
}
