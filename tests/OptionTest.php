<?php declare(strict_types=1);

namespace Kuria\Options;

use Kuria\DevMeta\Test;
use Kuria\Options\Option\LeafOption;
use Kuria\Options\Traits\OptionAssertionTrait;

class OptionTest extends Test
{
    use OptionAssertionTrait;

    /**
     * @dataProvider provideStaticFactories
     */
    function testShouldCreateOptionViaStaticFactory(string $staticMethod, array $args, array $expectedProps)
    {
        $this->assertOption($expectedProps, Option::{$staticMethod}(...$args));
    }

    function provideStaticFactories()
    {
        $nestedOption1 = new LeafOption('foo');
        $nestedOption2 = new LeafOption('bar');

        return [
            // staticMethod, args, expectedProps
            ['any', ['foo'], ['name' => 'foo', 'type' => null]],
            ['bool', ['bar'], ['name' => 'bar', 'type' => 'bool']],
            ['int', ['baz'], ['name' => 'baz', 'type' => 'int']],
            ['float', ['baz'], ['name' => 'baz', 'type' => 'float']],
            ['number', ['qux'], ['name' => 'qux', 'type' => 'number']],
            ['numeric', ['qux'], ['name' => 'qux', 'type' => 'numeric']],
            ['string', ['quux'], ['name' => 'quux', 'type' => 'string']],
            ['array', ['corge'], ['name' => 'corge', 'type' => 'array']],
            ['list', ['corge', 'string'], ['name' => 'corge', 'type' => 'string', 'list' => true]],
            ['iterable', ['lorem'], ['name' => 'lorem', 'type' => 'iterable']],
            ['object', ['ipsum'], ['name' => 'ipsum', 'type' => 'object']],
            ['object', ['ipsum', 'stdClass'], ['name' => 'ipsum', 'type' => 'stdClass']],
            ['resource', ['dolor'], ['name' => 'dolor', 'type' => 'resource']],
            ['scalar', ['sit'], ['name' => 'sit', 'type' => 'scalar']],
            ['choice', ['amet', 'foo', 'bar', 'baz'], ['name' => 'amet', 'choices' => ['foo', 'bar', 'baz']]],
            ['choiceList', ['consectetur', 1, 2, 3], ['name' => 'consectetur', 'choices' => [1, 2, 3], 'list' => true]],
            [
                'node',
                ['adipiscing', $nestedOption1, $nestedOption2],
                [
                    'name' => 'adipiscing',
                    'required' => false,
                    'children' => ['foo' => $nestedOption1, 'bar' => $nestedOption2],
                    'default' => [],
                ],
            ],
            [
                'nodeList',
                ['elit', $nestedOption1, $nestedOption2],
                [
                    'name' => 'elit',
                    'required' => false,
                    'list' => true,
                    'children' => ['foo' => $nestedOption1, 'bar' => $nestedOption2],
                    'default' => [],
                ],
            ],
        ];
    }
}
