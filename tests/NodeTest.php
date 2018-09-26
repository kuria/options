<?php declare(strict_types=1);

namespace Kuria\Options;

use Kuria\DevMeta\Test;
use Kuria\Options\Exception\CircularDependencyException;
use Kuria\Options\Exception\UnknownOptionException;

class NodeTest extends Test
{
    function testShouldGetPath()
    {
        $node = new Node(['foo', 'bar'], []);

        $this->assertSame(['foo', 'bar'], $node->getPath());
    }

    /**
     * @dataProvider provideToArrayMethods
     */
    function testShouldConvertToArray(string $method)
    {
        $node = $this->getNode();

        $this->assertSame(
            [
                'foo' => 'bar',
                'baz' => 'qux',
                'quux' => 'quuz',
            ],
            $node->{$method}()
        );
    }

    /**
     * @dataProvider provideToArrayMethods
     */
    function testShouldConvertNestedNodesToArray(string $method)
    {
        $node = new Node(
            [],
            [
                'foo' => 'bar',
                'baz' => new Node(
                    ['baz'],
                    [
                        'qux' => 'quux',
                        'quuz' => new Node(
                            ['baz', 'quux'],
                            [
                                'lorem' => 'ipsum',
                                'dolor' => 'sit',
                            ]
                        ),
                        'abc' => [
                            new Node(
                                ['baz', 'abc', 0],
                                [
                                    'def' => 'ghi',
                                    'jkl' => 'mno',
                                ]
                            ),
                            new Node(
                                ['baz', 'abc', 1],
                                [
                                    'pqr' => 'stu',
                                ]
                            ),
                            'xyz',
                        ],
                    ],
                    [
                        'sit_lazy' => static function () {
                            return 'lazy_sit_value';
                        },
                    ]
                )
            ],
            [
                'amet_lazy' => static function () {
                    return 'lazy_amet_value';
                },
            ]
        );

        $this->assertSame(
            [
                'foo' => 'bar',
                'baz' => [
                    'qux' => 'quux',
                    'quuz' => [
                        'lorem' => 'ipsum',
                        'dolor' => 'sit',
                    ],
                    'abc' => [
                        [
                            'def' => 'ghi',
                            'jkl' => 'mno',
                        ],
                        [
                            'pqr' => 'stu',
                        ],
                        'xyz',
                    ],
                    'sit_lazy' => 'lazy_sit_value',
                ],
                'amet_lazy' => 'lazy_amet_value',
            ],
            $node->{$method}()
        );
    }

    function provideToArrayMethods()
    {
        return [
            ['toArray'],
            ['__debugInfo'],
        ];
    }

    function testShouldCount()
    {
        $this->assertCount(3, $this->getNode());
    }

    function testShouldProvideArrayAccess()
    {
        $node = $this->getNode();

        // offsetExists
        $this->assertArrayHasKey('foo', $node);
        $this->assertArrayHasKey('baz', $node);
        $this->assertArrayHasKey('quux', $node);
        $this->assertArrayNotHasKey('lorem', $node);

        // offsetSet
        $node['lorem'] = 'ipsum';
        $this->assertArrayHasKey('lorem', $node);

        // offsetGet
        $this->assertSame('bar', $node['foo']);
        $this->assertSame('qux', $node['baz']);
        $this->assertSame('quuz', $node['quux']);
        $this->assertSame('ipsum', $node['lorem']);

        // offsetUnset
        unset($node['lorem']);
        $this->assertArrayNotHasKey('lorem', $node);
    }

    function testShouldProvideArrayAccessForNestedNodes()
    {
        $node = $this->getNestedNode();

        $node['baz']['quuz'] = 456;
        unset($node['baz']['qux']);

        $this->assertSame(456, $node['baz']['quuz']);

        $this->assertSame(
            [
                'foo' => 'bar',
                'baz' => [
                    'quuz' => 456,
                ],
            ],
            $node->toArray()
        );
    }

    function testShouldThrowExceptionWhenReadingUnknownOption()
    {
        $this->expectException(UnknownOptionException::class);
        $this->expectExceptionMessage('Unknown option nonexistent, known options are: foo, baz, quux');

        $this->getNode()['nonexistent'];
    }

    function testShouldThrowExceptionWhenReadingUnknownNestedOption()
    {
        $this->expectException(UnknownOptionException::class);
        $this->expectExceptionMessage('Unknown option baz[nonexistent], known options are: qux, quuz');

        $this->getNestedNode()['baz']['nonexistent'];
    }

    function testShouldInvokeLazyOptionCallableOnlyOnce()
    {
        $invocationCount = 0;

        $options = [];

        $node = new Node(
            [],
            $options,
            [
                'lazy' => function () use (&$invocationCount) {
                    ++$invocationCount;

                    return 'value';
                },
            ]
        );

        $this->assertSame(0, $invocationCount);
        $this->assertSame('value', $node['lazy']);
        $this->assertSame(1, $invocationCount);
        $this->assertSame('value', $node['lazy']);
        $this->assertSame(1, $invocationCount);
    }

    function testShouldDiscardLazyOptionIfOptionIsOverriden()
    {
        $node = $this->getNode();

        $node['quux'] = 'new quuz';

        $this->assertCount(3, $node);
        $this->assertSame('new quuz', $node['quux']);
    }

    /**
     * @dataProvider provideCircularDependencyNodePaths
     */
    function testShouldThrowExceptionOnCircularDependencyBetweenLazyOptions(array $path, string $expectedMessage)
    {
        $options = [];

        $node = new Node(
            $path,
            $options,
            [
                'foo' => function (Node $node) {
                    return $node['bar'];
                },
                'bar' => function (Node $node) {
                    return $node['baz'];
                },
                'baz' => function (Node $node) {
                    return $node['foo'];
                },
            ]
        );

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage($expectedMessage);

        $node['foo'];
    }

    function provideCircularDependencyNodePaths()
    {
        return [
            // path, expectedMessage
            [[], 'Recursive dependency detected between lazy options foo->bar->baz->foo'],
            [['foo', 'bar'], 'Recursive dependency detected at foo[bar] between lazy options foo->bar->baz->foo'],
        ];
    }

    function testShouldGetIterator()
    {
        $this->assertSame(
            [
                'foo' => 'bar',
                'baz' => 'qux',
                'quux' => 'quuz',
            ],
            iterator_to_array($this->getNode())
        );
    }

    private function getNode(): Node
    {
        $options = [
            'foo' => 'bar',
            'baz' => 'qux',
        ];

        return new Node(
            [],
            $options,
            [
                'quux' => function () {
                    return 'quuz';
                },
            ]
        );
    }

    private function getNestedNode(): Node
    {
        $nestedOptions = [
            'qux' => 'quux',
            'quuz' => 123,
        ];

        $options = [
            'foo' => 'bar',
            'baz' => new Node(['baz'], $nestedOptions),
        ];

        return new Node([], $options, []);
    }
}
