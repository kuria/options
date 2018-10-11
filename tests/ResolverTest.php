<?php declare(strict_types=1);

namespace Kuria\Options;

use Kuria\DevMeta\Test;
use Kuria\Options\Error\InvalidOptionError;
use Kuria\Options\Error\EmptyValueError;
use Kuria\Options\Error\Error;
use Kuria\Options\Error\InvalidChoiceError;
use Kuria\Options\Error\InvalidTypeError;
use Kuria\Options\Error\MissingOptionError;
use Kuria\Options\Error\NormalizerError;
use Kuria\Options\Error\UnknownOptionError;
use Kuria\Options\Exception\NormalizerException;
use Kuria\Options\Exception\ResolverException;
use Kuria\Options\Option\OptionDefinition;
use Kuria\Options\Option\LeafOption;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;

class ResolverTest extends Test
{
    function testShouldAddOptions()
    {
        $resolver = new Resolver();

        $foo = new LeafOption('foo');
        $bar = new LeafOption('bar');
        $baz = new LeafOption('baz');
        $newBaz = new LeafOption('baz');

        $resolver->addOption($foo);
        $resolver->addOption($bar, $baz);
        $resolver->addOption($newBaz);

        $this->assertTrue($resolver->hasOption('foo'));
        $this->assertTrue($resolver->hasOption('bar'));
        $this->assertTrue($resolver->hasOption('baz'));
        $this->assertFalse($resolver->hasOption('nonexistent'));

        $this->assertSame($foo, $resolver->getOption('foo'));
        $this->assertSame($bar, $resolver->getOption('bar'));
        $this->assertSame($newBaz, $resolver->getOption('baz'));
        $this->assertNull($resolver->getOption('nonexistent'));
        $this->assertSame(['foo' => $foo, 'bar' => $bar, 'baz' => $newBaz], $resolver->getOptions());
    }

    function testShouldRejectNonArrayData()
    {
        $resolver = new Resolver();

        $this->assertResolveFailure(
            $resolver,
            'foo',
            [new InvalidTypeError('array', false, 'foo')]
        );
    }

    /**
     * @dataProvider provideTypeInfos
     */
    function testShouldResolveValidValue(array $typeInfo)
    {
        $resolver = $this->createResolver(new LeafOption('option', $typeInfo['type']));

        foreach ($typeInfo['validValues'] as $key => $validValue) {
            $this->assertResolveSuccess(
                $resolver,
                ['option' => $validValue],
                null,
                [],
                "expected success with valid value \"{$key}\""
            );
        }
    }

    /**
     * @dataProvider provideTypeInfos
     */
    function testShouldResolveEmptyValue(array $typeInfo)
    {
        if (empty($typeInfo['emptyValues'])) {
            $this->addToAssertionCount(1);
            return;
        }

        $resolver = $this->createResolver(new LeafOption('option', $typeInfo['type']));

        foreach ($typeInfo['emptyValues'] as $key => $emptyValue) {
            $this->assertResolveSuccess(
                $resolver,
                ['option' => $emptyValue],
                null,
                [],
                "expected success with empty value \"{$key}\""
            );
        }
    }

    /**
     * @dataProvider provideTypeInfos
     */
    function testShouldRejectEmptyValueIfOptionDoesNotAllowEmpty(array $typeInfo)
    {
        if (empty($typeInfo['emptyValues'])) {
            $this->addToAssertionCount(1);
            return;
        }

        $resolver = $this->createResolver((new LeafOption('option', $typeInfo['type']))->notEmpty());

        foreach ($typeInfo['emptyValues'] as $key => $emptyValue) {
            $this->assertResolveFailure(
                $resolver,
                ['option' => $emptyValue],
                [(new EmptyValueError(false, $emptyValue))->at(['option'])],
                "expected failure with empty value \"{$key}\""
            );
        }
    }

    /**
     * @dataProvider provideTypeInfos
     */
    function testShouldAcceptNullIfOptionIsNullable(array $typeInfo)
    {
        $this->assertResolveSuccess(
            $this->createResolver((new LeafOption('option', $typeInfo['type']))->nullable()),
            ['option' => null]
        );
    }

    /**
     * @dataProvider provideTypeInfos
     */
    function testShouldAcceptNullIfOptionIsNullableAndDoesNotAllowEmpty(array $typeInfo)
    {
        $this->assertResolveSuccess(
            $this->createResolver((new LeafOption('option', $typeInfo['type']))->nullable()->notEmpty()),
            ['option' => null]
        );
    }

    /**
     * @dataProvider provideTypeInfos
     */
    function testShouldRejectNullIfOptionIsNotNullable(array $typeInfo)
    {
        $this->assertResolveFailure(
            $this->createResolver(new LeafOption('option', $typeInfo['type'])),
            ['option' => null],
            [(new InvalidTypeError($typeInfo['type'], false, null))->at(['option'])]
        );
    }

    /**
     * @dataProvider provideTypeInfos
     */
    function testShouldResolveListOption(array $typeInfo)
    {
        $this->assertResolveSuccess(
            $this->createResolver(Option::list('option', $typeInfo['type'])),
            ['option' => array_merge($typeInfo['validValues'], $typeInfo['emptyValues'])]
        );
    }

    /**
     * @dataProvider provideTypeInfos
     */
    function testShouldRejectInvalidListOption(array $typeInfo)
    {
        if (empty($typeInfo['invalidValues'])) {
            $this->addToAssertionCount(1);
            return;
        }

        $expectedErrors = [];

        foreach ($typeInfo['invalidValues'] as $key => $invalidValue) {
            $expectedErrors[] = (new InvalidTypeError($typeInfo['type'], false, $invalidValue))->at(['option', $key]);
        }

        $this->assertResolveFailure(
            $this->createResolver(Option::list('option', $typeInfo['type'])),
            ['option' => $typeInfo['invalidValues']],
            $expectedErrors
        );
    }

    /**
     * @dataProvider provideTypeInfos
     */
    function testShouldRejectNullValuesInListOption(array $typeInfo)
    {
        $this->assertResolveFailure(
            $this->createResolver(Option::list('option', $typeInfo['type'])),
            ['option' => $typeInfo['validValues'] + ['null_item' => null]],
            [(new InvalidTypeError($typeInfo['type'], false, null))->at(['option', 'null_item'])]
        );
    }

    /**
     * @dataProvider provideTypeInfos
     */
    function testShouldRejectEmptyValuesInListIfOptionDoesNotAllowEmpty()
    {
        if (empty($typeInfo['emptyValues'])) {
            $this->addToAssertionCount(1);
            return;
        }

        $expectedErrors = [];

        foreach ($typeInfo['emptyValues'] as $key => $emptyValue) {
            $expectedErrors[] = (new EmptyValueError(false, $emptyValue))->at(['option', $key]);
        }

        $this->assertResolveFailure(
            $this->createResolver(Option::list('option', $typeInfo['type'])),
            ['option' => $typeInfo['emptyValues']],
            $expectedErrors
        );
    }

    /**
     * @dataProvider provideTypeInfos
     */
    function testShouldAcceptNullListIfOptionIsNullable(array $typeInfo)
    {
        $this->assertResolveSuccess(
            $this->createResolver(Option::list('option', $typeInfo['type'])->nullable()),
            ['option' => null]
        );
    }

    function testShouldNormalizeOption()
    {
        $resolver = $this->createResolver(
            Option::string('string')
                ->normalize(static function (string $value) {
                    static::assertSame(1, func_num_args(), 'Expected a single normalizer argument');

                    return $value . '.bar';
                })
                ->normalize(static function (string $value) {
                    static::assertSame(1, func_num_args(), 'Expected a single normalizer argument');

                    return $value . '.baz';
                })
        );

        $this->assertResolveSuccess($resolver, ['string' => 'foo'], ['string' => 'foo.bar.baz']);
    }

    function testShouldNormalizeOptionWithContext()
    {
        $resolver = $this->createResolver(
            Option::any('option')->normalize(static function ($value, $foo, $bar) {
                static::assertSame('value', $value);
                static::assertSame('foo', $foo);
                static::assertSame(123, $bar);

                return $value;
            })
        );

        $this->assertResolveSuccess($resolver, ['option' => 'value'], null, ['foo', 123]);
    }

    function testShouldHandleNormalizerException()
    {
        $errors = [new InvalidOptionError('foo'), new InvalidOptionError('bar'), new InvalidOptionError('baz')];
        $exception = new NormalizerException('test exception', $errors);
        $this->removeExceptionTrace($exception);

        $resolver = $this->createResolver(
            Option::any('option')
                ->normalize(static function () use ($exception) {
                    throw $exception;
                })
                ->normalize(static function () {
                    static::fail('Normalization should stop after first exception');
                })
        );

        $this->assertResolveFailure($resolver, ['option' => 'value'], $errors);
    }

    function testShouldNotNormalizeOptionWithInvalidValue()
    {
        $resolver = $this->createResolver(
            Option::string('string')->normalize(static function () {
                self::fail('Normalizers should not be called with invalid values');
            })
        );

        $this->expectException(ResolverException::class);

        $resolver->resolve(['string' => 123]);
    }

    function testShouldValidateOption()
    {
        $validatorCalled = false;
        $validator2Called = false;
        $listValidatorCalled = false;

        $resolver = $this->createResolver(
            Option::any('option')
                ->validate(static function ($value) use (&$validatorCalled, &$validator2Called) {
                    static::assertSame(1, func_num_args(), 'Expected a single validator argument');
                    static::assertFalse($validatorCalled, 'Each validator should be called only once');
                    static::assertFalse($validator2Called, 'Expected first validator to be called first');
                    static::assertSame('value', $value);

                    $validatorCalled = true;
                })
                ->validate(static function ($value) use (&$validatorCalled, &$validator2Called) {
                    static::assertSame(1, func_num_args(), 'Expected a single validator argument');
                    static::assertTrue($validatorCalled, 'Expected second validator to be called after the first');
                    static::assertFalse($validator2Called, 'Each validator should be called only once');
                    static::assertSame('value', $value);

                    $validator2Called = true;
                }),
            Option::list('listOption', null)
                ->validate(static function ($value) use (&$listValidatorCalled) {
                    static::assertSame(1, func_num_args(), 'Expected a single validator argument');
                    static::assertSame([1, 2, 3], $value);

                    $listValidatorCalled = true;
                })
        );

        $this->assertResolveSuccess($resolver, ['option' => 'value', 'listOption' => [1, 2, 3]]);
        $this->assertTrue($validatorCalled);
        $this->assertTrue($validator2Called);
        $this->assertTrue($listValidatorCalled);
    }

    function testShouldValidateOptionWithContext()
    {
        $resolver = $this->createResolver(
            Option::any('option')
                ->validate(static function ($value, $foo, $bar) {
                    static::assertSame('value', $value);
                    static::assertSame('foo', $foo);
                    static::assertSame(123, $bar);
                })
        );

        $this->assertResolveSuccess($resolver, ['option' => 'value'], null, ['foo', 123]);
    }

    function testShouldHandleValidatorErrors()
    {
        $resolver = $this->createResolver(
            Option::any('option')
                ->validate(static function () {
                    return [
                        new InvalidOptionError('foo'),
                        new InvalidOptionError('bar'),
                    ];
                })
                ->validate(static function () {
                    static::fail('Validation should stop after first failure');
                })
        );

        $this->assertResolveFailure(
            $resolver,
            ['option' => 'value'],
            [
                (new InvalidOptionError('foo'))->at(['option']),
                (new InvalidOptionError('bar'))->at(['option']),
            ]
        );
    }

    function testShouldNotValidateOptionWithInvalidValue()
    {
        $validator = static function () {
            static::fail('Validators should not be called with invalid values');
        };

        $resolver = $this->createResolver(
            Option::string('string')->validate($validator),
            Option::list('stringList', 'string')->validate($validator)
        );

        $this->assertResolveFailure(
            $resolver,
            ['string' => 123, 'stringList' => ['foo', 456]],
            [
                (new InvalidTypeError('string', false, 123))->at(['string']),
                (new InvalidTypeError('string', false, 456))->at(['stringList', 1]),
            ]
        );
    }

    function testShouldNormalizeAndValidateListOptions()
    {
        $validatorCalled = false;

        $resolver = $this->createResolver(
            Option::list('option', null)
                ->normalize('array_reverse')
                ->validate(static function (array $value) use (&$validatorCalled) {
                    static::assertSame(1, func_num_args(), 'Expected a single validator argument');
                    static::assertSame([3, 2, 1], $value);

                    $validatorCalled = true;
                })
        );

        $this->assertResolveSuccess($resolver, ['option' => [1, 2, 3]], ['option' => [3, 2, 1]]);
        $this->assertTrue($validatorCalled);
    }

    function testShouldNormalizeBeforeValidation()
    {
        $normalizerCalled = false;
        $validatorCalled = false;

        $resolver = $this->createResolver(
            Option::any('option')
                ->normalize(static function ($value) use (&$normalizerCalled, &$validatorCalled) {
                    static::assertFalse($normalizerCalled, 'Normalizers should be called only once');
                    static::assertFalse($validatorCalled, 'Normalizers should be called before validators');
                    static::assertSame('value', $value);

                    $normalizerCalled = true;

                    return 'normalized-value';
                })
                ->validate(static function ($value) use (&$normalizerCalled, &$validatorCalled) {
                    static::assertTrue($normalizerCalled, 'Validators should be called after normalizers');
                    static::assertFalse($validatorCalled, 'Validators should be called only once');
                    static::assertSame('normalized-value', $value);

                    $validatorCalled = true;
                })
        );

        $this->assertResolveSuccess($resolver, ['option' => 'value'], ['option' => 'normalized-value']);

        $this->assertTrue($normalizerCalled);
        $this->assertTrue($validatorCalled);
    }

    function testShouldResolveDefaults()
    {
        $lazyCalled = false;
        $closure = static function () {};

        $resolver = $this->createResolver(
            Option::any('option')->default('example'),
            Option::int('intOption')
                ->default('foo')
                ->normalize(static function () {
                    static::fail('Leaf option default values should not be normalized');
                })
                ->validate(static function () {
                    static::fail('Leaf option default values should not be validated');
                }),
            Option::any('lazy')->default(static function (Node $node) use (&$lazyCalled) {
                $lazyCalled = true;

                return "option is {$node['option']}";
            }),
            Option::any('closure')->default($closure)
        );

        $node = $resolver->resolve([]);

        $this->assertTrue($node->offsetExists('lazy'));
        $this->assertFalse($lazyCalled);
        $this->assertSame(
            [
                'option' => 'example',
                'intOption' => 'foo',
                'closure' => $closure,
                'lazy' => 'option is example',
            ],
            $node->toArray()
        );
        $this->assertTrue($lazyCalled);
    }

    /**
     * @dataProvider provideChoices
     */
    function testShouldResolveChoices(LeafOption $option, array $validValues, array $invalidValues, array $expectedErrorMap)
    {
        $resolver = $this->createResolver($option);

        foreach ($validValues as $key => $validValue) {
            $this->assertResolveSuccess(
                $resolver,
                [$option->name => $validValue],
                null,
                [],
                "expected success valid value \"{$key}\""
            );
        }

        foreach ($invalidValues as $key => $invalidValue) {
            $this->assertResolveFailure(
                $resolver,
                [$option->name => $invalidValue],
                $expectedErrorMap[$key],
                "expected failure with invalid value \"{$key}\""
            );
        }
    }

    function provideChoices()
    {
        $objectA = new \stdClass();
        $objectB = new \stdClass();
        $objectC = new \stdClass();
        $otherObject = new \stdClass();

        return [
            // option, validValues, invalidValues, expectedErrorMap
            'scalars' => [
                Option::choice('option', 1, 2, 3),
                [1, 2, 3],
                ['1', 2.1],
                [
                    [(new InvalidChoiceError([1, 2, 3], false, '1'))->at(['option'])],
                    [(new InvalidChoiceError([1, 2, 3], false, 2.1))->at(['option'])],
                    [(new InvalidTypeError(null, false, null))->at(['option'])],
                ],
            ],

            'objects' => [
                Option::choice('option', $objectA, $objectB, $objectC),
                [$objectA, $objectB, $objectC],
                [$otherObject, 123, null],
                [
                    [(new InvalidChoiceError([$objectA, $objectB, $objectC], false, $otherObject))->at(['option'])],
                    [(new InvalidChoiceError([$objectA, $objectB, $objectC], false, 123))->at(['option'])],
                    [(new InvalidTypeError(null, false, null))->at(['option'])],
                ],
            ],

            'nullable' => [
                Option::choice('option', 'foo', 'bar', 'baz')->nullable(),
                ['foo', 'bar', 'baz', null],
                ['qux', 123, ''],
                [
                    [(new InvalidChoiceError(['foo', 'bar', 'baz'], true, 'qux'))->at(['option'])],
                    [(new InvalidChoiceError(['foo', 'bar', 'baz'], true, 123))->at(['option'])],
                    [(new InvalidChoiceError(['foo', 'bar', 'baz'], true, ''))->at(['option'])],
                ],
            ],

            'list' => [
                Option::choiceList('option', 'a', 'b', 'c'),
                [['a', 'b', 'c', 'a'], ['a'], []],
                [['a', 'x', 'c', 'z'], [null, 'a']],
                [
                    [
                        (new InvalidChoiceError(['a', 'b', 'c'], false, 'x'))->at(['option', 1]),
                        (new InvalidChoiceError(['a', 'b', 'c'], false, 'z'))->at(['option', 3]),
                    ],
                    [
                        (new InvalidTypeError(null, false, null))->at(['option', 0]),
                    ],
                ],
            ],

            'nullable list' => [
                Option::choiceList('option', 1, 2, 3)->nullable(),
                [[1, 2, 3], [2], [], null],
                [[1, 2, 3, null], [3, 4, 5]],
                [
                    [
                        (new InvalidTypeError(null, false, null))->at(['option', 3]),
                    ],
                    [
                        (new InvalidChoiceError([1, 2, 3], false, 4))->at(['option', 1]),
                        (new InvalidChoiceError([1, 2, 3], false, 5))->at(['option', 2]),
                    ],
                ],
            ],

            'not-empty choice list' => [
                Option::choiceList('option', 'x', 'y', 'z')->notEmpty(),
                [['x', 'y'], ['z']],
                [[], ['abc']],
                [
                    [
                        (new EmptyValueError(false, []))->at(['option']),
                    ],
                    [
                        (new InvalidChoiceError(['x', 'y', 'z'], false, 'abc'))->at(['option', 0]),
                    ],
                ],
            ],
        ];
    }

    function testShouldDetectMissingRequiredOption()
    {
        $resolver = $this->createResolver(
            Option::string('foo'),
            Option::string('bar'),
            Option::string('baz')
        );

        $this->assertResolveFailure(
            $resolver,
            ['foo' => '123'],
            [
                (new MissingOptionError())->at(['bar']),
                (new MissingOptionError())->at(['baz']),
            ]
        );
    }

    function testShouldDetectUnknownOptions()
    {
        $resolver = $this->createResolver(Option::string('foo'));

        $this->assertFalse($resolver->isIgnoringUnknown());

        $this->assertResolveFailure(
            $resolver,
            ['foo' => 'bar', 'baz' => 'qux', 'quux' => 'quuz'],
            [
                (new UnknownOptionError())->at(['baz']),
                (new UnknownOptionError())->at(['quux']),
            ]
        );
    }

    function testShouldIgnoreUnknownOptionsIfEnabled()
    {
        $resolver = $this->createResolver(Option::string('foo'));
        $resolver->setIgnoreUnknown(true);

        $this->assertTrue($resolver->isIgnoringUnknown());
        $this->assertResolveSuccess($resolver, ['foo' => 'bar', 'baz' => 'qux', 'quux' => 'quuz']);
    }

    function testShouldResolveNodeOptions()
    {
        $resolver = $this->createResolver(
            Option::string('name'),
            Option::int('score')->default(0),
            Option::node(
                'props',
                Option::int('a')->default(0),
                Option::int('b')->default(0),
                Option::int('c')->default(0)
            ),
            Option::nodeList(
                'log',
                Option::string('name'),
                Option::choice('type', 1, 2, 3),
                Option::node(
                    'location',
                    Option::int('zone'),
                    Option::number('x'),
                    Option::number('y')
                )
            )
        );

        // resolve with full data
        $fullData = [
            'name' => 'Foo',
            'score' => 123,
            'props' => [
                'a' => 59,
                'b' => 33,
                'c' => 7,
            ],
            'log' => [
                [
                    'name' => 'Bar',
                    'type' => 1,
                    'location' => [
                        'zone' => 456,
                        'x' => 33.5,
                        'y' => 42.8,
                    ],
                ],
                [
                    'name' => 'Baz',
                    'type' => 2,
                    'location' => [
                        'zone' => 789,
                        'x' => 10,
                        'y' => 6,
                    ],
                ],
            ],
        ];

        $node = $resolver->resolve($fullData);

        $this->assertInstanceOf(Node::class, $node['props']);
        $this->assertSame(['props'], $node['props']->getPath());
        $this->assertContainsOnlyInstancesOf(Node::class, $node['log']);
        $this->assertCount(2, $node['log']);
        $this->assertSame(['log', 0], $node['log'][0]->getPath());
        $this->assertSame(['log', 1], $node['log'][1]->getPath());
        $this->assertSame($fullData, $node->toArray());

        // resolve with minimal data
        $node = $resolver->resolve(['name' => 'Bar']);

        $this->assertInstanceOf(Node::class, $node['props']);
        $this->assertContainsOnlyInstancesOf(Node::class, $node['log']);
        $this->assertSame(
            [
                'name' => 'Bar',
                'score' => 0,
                'props' => [
                    'a' => 0,
                    'b' => 0,
                    'c' => 0,
                ],
                'log' => [],
            ],
            $node->toArray()
        );
    }

    function testShouldResolveRequiredNodeOption()
    {
        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::string('foo')->default('abc'),
                Option::int('bar')->default(123)
            )->required()
        );

        $this->assertResolveFailure(
            $resolver,
            [],
            [(new MissingOptionError())->at(['node'])]
        );

        $this->assertResolveSuccess(
            $resolver,
            ['node' => []],
            [
                'node' => [
                    'foo' => 'abc',
                    'bar' => 123,
                ]
            ]
        );
    }

    function testShouldResolveNodeOptionDefaults()
    {
        $lazyRootCalled = false;
        $lazyQuxCalled = false;

        $resolver = $this->createResolver(
            Option::string('lazyRoot')->default(static function (Node $node) use (&$lazyRootCalled) {
                $lazyRootCalled = true;

                return "node baz is {$node['node']['baz']}";
            }),
            Option::node(
                'node',
                Option::string('foo'),
                Option::int('bar'),
                Option::int('baz')->default(456),
                Option::string('qux')->default(static function (Node $node) use (&$lazyQuxCalled) {
                    $lazyQuxCalled = true;

                    return "bar is {$node['bar']}";
                })
            )->default([
                'foo' => 'default foo',
                'bar' => 123,
            ]),
            Option::nodeList(
                'nodeList',
                Option::int('x'),
                Option::int('y'),
                Option::int('z')->default(0)
            )->default([
                ['x' => 0, 'y' => 1],
                ['x' => 50, 'y' => 100],
            ])
        );

        $node = $resolver->resolve([]);
        $this->assertTrue(isset($node['lazyRoot']));
        $this->assertTrue(isset($node['node']['qux']));
        $this->assertInstanceOf(Node::class, $node['node']);
        $this->assertSame(['node'], $node['node']->getPath());
        $this->assertContainsOnlyInstancesOf(Node::class, $node['nodeList']);
        $this->assertCount(2, $node['nodeList']);
        $this->assertSame(['nodeList', 0], $node['nodeList'][0]->getPath());
        $this->assertSame(['nodeList', 1], $node['nodeList'][1]->getPath());
        $this->assertFalse($lazyRootCalled);
        $this->assertFalse($lazyQuxCalled);
        $this->assertSame(
            [
                'node' => [
                    'foo' => 'default foo',
                    'bar' => 123,
                    'baz' => 456,
                    'qux' => 'bar is 123',
                ],
                'nodeList' => [
                    ['x' => 0, 'y' => 1, 'z' => 0],
                    ['x' => 50, 'y' => 100, 'z' => 0],
                ],
                'lazyRoot' => 'node baz is 456',
            ],
            $node->toArray()
        );
        $this->assertTrue($lazyRootCalled);
        $this->assertTrue($lazyQuxCalled);
    }

    function testShouldResolveNodeOptionWithNullDefault()
    {
        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::string('foo'),
                Option::int('bar'),
                Option::node(
                    'innerNode',
                    Option::string('baz')
                )
            )->default(null)
        );

        $this->assertResolveSuccess(
            $resolver,
            [],
            [
                'node' => null,
            ]
        );
    }

    function testShouldResolveNullableNodeOption()
    {
        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::string('foo'),
                Option::string('bar')
            )->nullable()
        );

        $this->assertResolveSuccess(
            $resolver,
            ['node' => null]
        );
    }

    function testShouldResolveOptionalNotNullableNodeOptionWithNullDefault()
    {
        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::string('foo'),
                Option::string('bar')
            )->default(null)->notNullable()
        );

        $this->assertResolveSuccess(
            $resolver,
            [],
            ['node' => null]
        );

        $this->assertResolveFailure(
            $resolver,
            ['node' => null],
            [(new InvalidTypeError('array', false, null))->at(['node'])]
        );
    }

    function testShouldNormalizeNodeOptions()
    {
        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::node(
                    'innerNode',
                    Option::any('option'),
                    Option::any('defaultOption')->default('bar')
                )->normalize(static function (Node $node) {
                    static::assertSame(1, func_num_args(), 'Expected a single node normalizer argument');

                    $node['option'] .= '.quux';
                    $node['defaultOption'] .= '.quuz';

                    return $node;
                }),
                Option::nodeList(
                    'nodeList',
                    Option::any('value')->default('default')
                )->normalize(static function (array $nodes) {
                    static::assertSame(1, func_num_args(), 'Expected a single node normalizer argument');

                    return array_map(static function (Node $node) { return (object) $node->toArray(); }, $nodes);
                })->normalize(static function (array $nodes) {
                    static::assertSame(1, func_num_args(), 'Expected a single node normalizer argument');

                    return array_map(static function (\stdClass $node) { $node->value .= '.quux'; return $node; }, $nodes);
                })
            )->normalize(static function (Node $node) {
                static::assertSame(1, func_num_args(), 'Expected a single node normalizer argument');

                return $node->toArray();
            })->normalize(static function (array $node) {
                static::assertSame(1, func_num_args(), 'Expected a single node normalizer argument');

                return array_change_key_case($node, CASE_UPPER);
            })
        );

        $this->assertResolveSuccess(
            $resolver,
            [
                'node' => [
                    'innerNode' => [
                        'option' => 'foo',
                    ],
                    'nodeList' => [
                        ['value' => 'foo'],
                        ['value' => 'bar'],
                        [],
                    ],
                ],
            ],
            [
                'node' => [
                    'INNERNODE' => [
                        'option' => 'foo.quux',
                        'defaultOption' => 'bar.quuz',
                    ],
                    'NODELIST' => [
                        (object) ['value' => 'foo.quux'],
                        (object) ['value' => 'bar.quux'],
                        (object) ['value' => 'default.quux'],
                    ],
                ],
            ]
        );
    }

    function testShouldNormalizeNodeOptionsWithContext()
    {
        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::node(
                    'innerNode',
                    Option::any('option')
                )->normalize(static function (Node $node, $foo, $bar) {
                    static::assertSame('foo', $foo);
                    static::assertSame(123, $bar);

                    return $node;
                }),
                Option::nodeList(
                    'nodeList',
                    Option::any('value')
                )->normalize(static function (array $nodes, $foo, $bar) {
                    static::assertSame('foo', $foo);
                    static::assertSame(123, $bar);

                    return $nodes;
                })->normalize(static function (array $nodes, $foo, $bar) {
                    static::assertSame('foo', $foo);
                    static::assertSame(123, $bar);

                    return $nodes;
                })
            )->normalize(static function (Node $node, $foo, $bar) {
                static::assertSame('foo', $foo);
                static::assertSame(123, $bar);

                return $node;
            })->normalize(static function (Node $node, $foo, $bar) {
                static::assertSame('foo', $foo);
                static::assertSame(123, $bar);

                return $node;
            })
        );

        $this->assertResolveSuccess(
            $resolver,
            [
                'node' => [
                    'innerNode' => [
                        'option' => 'foo',
                    ],
                    'nodeList' => [
                        ['value' => 'foo'],
                        ['value' => 'bar'],
                    ],
                ],
            ],
            null,
            ['foo', 123]
        );
    }

    function testShouldHandleNodeOptionNormalizerErrors()
    {
        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::any('option')
            )->normalize(static function () {
                throw new NormalizerException('', [new InvalidOptionError('foo')]);
            }) ->normalize(static function () {
                static::fail('Node normalization should stop after first failure');
            })
        );

        $this->assertResolveFailure(
            $resolver,
            [
                'node' => ['option' => 'value'],
            ],
            [
                (new InvalidOptionError('foo'))->at(['node']),
            ]
        );
    }

    function testShouldHandleNodeListOptionNormalizerErrors()
    {
        $resolver = $this->createResolver(
            Option::nodeList(
                'nodeList',
                Option::any('option')
            )->normalize(static function () {
                throw new NormalizerException('', [new InvalidOptionError('foo')]);
            }) ->normalize(static function () {
                static::fail('Node list normalization should stop after first failure');
            })
        );

        $this->assertResolveFailure(
            $resolver,
            [
                'nodeList' => [
                    ['option' => 'value'],
                ],
            ],
            [
                (new InvalidOptionError('foo'))->at(['nodeList']),
            ]
        );
    }

    function testShouldStopNormalizingNodeOptionsAfterInnerNodeNormalizerError()
    {
        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::node(
                    'innerNode',
                    Option::any('option')
                )->normalize(static function () {
                    throw new NormalizerException('', [new InvalidOptionError('foo')]);
                })
            )->normalize(static function () {
                static::fail('Node normalization should stop after inner normalizer error');
            })
        );

        $this->assertResolveFailure(
            $resolver,
            [
                'node' => [
                    'innerNode' => ['option' => 'value'],
                ],
            ],
            [
                (new InvalidOptionError('foo'))->at(['node', 'innerNode']),
            ]
        );
    }

    function testShouldStopNormalizingNodeOptionsAfterInnerNodeListNormalizerError()
    {
        $resolver = $this->createResolver(
            Option::nodeList(
                'nodeList',
                Option::nodeList(
                    'innerNodeList',
                    Option::any('option')
                )->normalize(static function () {
                    throw new NormalizerException('', [new InvalidOptionError('foo')]);
                })
            )->normalize(static function () {
                static::fail('Node list normalization should stop after inner normalizer error');
            })
        );

        $this->assertResolveFailure(
            $resolver,
            [
                'nodeList' => [
                    ['innerNodeList' => [['option' => 'value']]],
                ],
            ],
            [
                (new InvalidOptionError('foo'))->at(['nodeList', 0, 'innerNodeList']),
            ]
        );
    }

    function testShouldNotNormalizeNodeOptionsIfThereAreAnyErrors()
    {
        $normalizer = static function () {
            static::fail('Node normalizers should not be called if there are errors');
        };

        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::node(
                    'innerNode',
                    Option::string('string')
                )->normalize($normalizer),
                Option::nodeList(
                    'nodeList',
                    Option::string('string')
                )->normalize($normalizer)
            )
        );

        $this->assertResolveFailure(
            $resolver,
            [
                'node' => [
                    'innerNode' => [
                        'string' => 123,
                    ],
                    'nodeList' => [
                        ['string' => 'foo'],
                        ['string' => 456],
                    ],
                ],
            ],
            [
                (new InvalidTypeError('string', false, 123))->at(['node', 'innerNode', 'string']),
                (new InvalidTypeError('string', false, 456))->at(['node', 'nodeList', 1, 'string']),
            ]
        );
    }

    function testShouldValidateNodeOptions()
    {
        /** @var bool[] $validatorCallStatus */
        $validatorCallStatus = [
            'node' => false,
            'node2' => false,
            'innerNode' => false,
            'innerNode2' => false,
            'list' => false,
            'list2' => false,
        ];

        $expectedResolvedArray = [
            'node' => [
                'innerNode' => [
                    'option' => 'foo',
                    'defaultOption' => 'bar',
                ],
                'nodeList' => [
                    ['value' => 1],
                    ['value' => 2],
                    ['value' => 'default'],
                ],
            ],
        ];

        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::node(
                    'innerNode',
                    Option::any('option'),
                    Option::any('defaultOption')->default('bar')
                )->validate(static function (Node $node) use (&$validatorCallStatus, $expectedResolvedArray) {
                    static::assertSame(1, func_num_args(), 'Expected a single node validator argument');
                    static::assertFalse($validatorCallStatus['node'], 'Nested node validators should be called before outer node validators');
                    static::assertFalse($validatorCallStatus['node2'], 'Nested node validators should be called before outer node validators');
                    static::assertFalse($validatorCallStatus['innerNode'], 'Each inner validator should be called only once');
                    static::assertFalse($validatorCallStatus['innerNode2'], 'Expected first inner validator to be called first');
                    static::assertSame($expectedResolvedArray['node']['innerNode'], $node->toArray());

                    $validatorCallStatus['innerNode'] = true;
                })->validate(static function (Node $node) use (&$validatorCallStatus, $expectedResolvedArray) {
                    static::assertSame(1, func_num_args(), 'Expected a single node validator argument');
                    static::assertFalse($validatorCallStatus['node'], 'Nested node validators should be called before outer node validators');
                    static::assertFalse($validatorCallStatus['node2'], 'Nested node validators should be called before outer node validators');
                    static::assertTrue($validatorCallStatus['innerNode'], 'Expected second inner validator to be called after the first');
                    static::assertFalse($validatorCallStatus['innerNode2'], 'Each inner validator should be called only once');
                    static::assertSame($expectedResolvedArray['node']['innerNode'], $node->toArray());

                    $validatorCallStatus['innerNode2'] = true;
                }),
                Option::nodeList(
                    'nodeList',
                    Option::any('value')->default('default')
                )->validate(static function (array $nodes) use (&$validatorCallStatus, $expectedResolvedArray) {
                    static::assertSame(1, func_num_args(), 'Expected a single node list validator argument');
                    static::assertFalse($validatorCallStatus['node'], 'Nested node list validators should be called before outer node validators');
                    static::assertFalse($validatorCallStatus['node2'], 'Nested node list validators should be called before outer node validators');
                    static::assertFalse($validatorCallStatus['list'], 'Each list validator should be called only once');
                    static::assertFalse($validatorCallStatus['list2'], 'Expected first list validator to be called first');
                    static::assertContainsOnlyInstancesOf(Node::class, $nodes);
                    static::assertSame($expectedResolvedArray['node']['nodeList'][0], $nodes[0]->toArray());
                    static::assertSame($expectedResolvedArray['node']['nodeList'][1], $nodes[1]->toArray());
                    static::assertSame($expectedResolvedArray['node']['nodeList'][2], $nodes[2]->toArray());

                    $validatorCallStatus['list'] = true;
                })->validate(static function (array $nodes) use (&$validatorCallStatus, $expectedResolvedArray) {
                    static::assertSame(1, func_num_args(), 'Expected a single node list validator argument');
                    static::assertFalse($validatorCallStatus['node'], 'Nested node list validators should be called before outer node validators');
                    static::assertFalse($validatorCallStatus['node2'], 'Nested node list validators should be called before outer node validators');
                    static::assertTrue($validatorCallStatus['list'], 'Expected second list validator to be called after the first');
                    static::assertFalse($validatorCallStatus['list2'], 'Each list validator should be called only once');
                    static::assertContainsOnlyInstancesOf(Node::class, $nodes);
                    static::assertSame($expectedResolvedArray['node']['nodeList'][0], $nodes[0]->toArray());
                    static::assertSame($expectedResolvedArray['node']['nodeList'][1], $nodes[1]->toArray());
                    static::assertSame($expectedResolvedArray['node']['nodeList'][2], $nodes[2]->toArray());

                    $validatorCallStatus['list2'] = true;
                })
            )->validate(static function (Node $node) use (&$validatorCallStatus, $expectedResolvedArray) {
                static::assertSame(1, func_num_args(), 'Expected a single node validator argument');
                static::assertTrue($validatorCallStatus['list'], 'Node validators should be called after inner node validators');
                static::assertTrue($validatorCallStatus['list2'], 'Node validators should be called after inner node validators');
                static::assertFalse($validatorCallStatus['node'], 'Each validator should be called only once');
                static::assertFalse($validatorCallStatus['node2'], 'Expected first validator to be called first');
                static::assertSame($expectedResolvedArray['node'], $node->toArray());

                $validatorCallStatus['node'] = true;
            })->validate(static function (Node $node) use (&$validatorCallStatus, $expectedResolvedArray) {
                static::assertSame(1, func_num_args(), 'Expected a single node validator argument');
                static::assertTrue($validatorCallStatus['list'], 'Node validators should be called after inner node validators');
                static::assertTrue($validatorCallStatus['list2'], 'Node validators should be called after inner node validators');
                static::assertTrue($validatorCallStatus['node'], 'Expected second validator to be called after the first');
                static::assertFalse($validatorCallStatus['node2'], 'Each validator should be called only once');
                static::assertSame($expectedResolvedArray['node'], $node->toArray());

                $validatorCallStatus['node2'] = true;
            })
        );

        $this->assertResolveSuccess(
            $resolver,
            [
                'node' => [
                    'innerNode' => [
                        'option' => 'foo',
                    ],
                    'nodeList' => [
                        ['value' => 1],
                        ['value' => 2],
                        [],
                    ],
                ],
            ],
            $expectedResolvedArray
        );

        $this->assertSame(
            ['node' => true, 'node2' => true, 'innerNode' => true, 'innerNode2' => true, 'list' => true, 'list2' => true],
            $validatorCallStatus
        );
    }

    function testShouldValidateNodeOptionsWithContext()
    {
        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::node(
                    'innerNode',
                    Option::any('option')
                )->validate(static function (Node $node, $foo, $bar) {
                    static::assertSame('foo', $foo);
                    static::assertSame(123, $bar);
                }),
                Option::nodeList(
                    'nodeList',
                    Option::any('value')
                )->validate(static function (array $nodes, $foo, $bar) {
                    static::assertSame('foo', $foo);
                    static::assertSame(123, $bar);
                })->validate(static function (array $nodes, $foo, $bar) {
                    static::assertSame('foo', $foo);
                    static::assertSame(123, $bar);
                })
            )->validate(static function (Node $node, $foo, $bar) {
                static::assertSame('foo', $foo);
                static::assertSame(123, $bar);
            })->validate(static function (Node $node, $foo, $bar) {
                static::assertSame('foo', $foo);
                static::assertSame(123, $bar);
            })
        );

        $this->assertResolveSuccess(
            $resolver,
            [
                'node' => [
                    'innerNode' => [
                        'option' => 'foo',
                    ],
                    'nodeList' => [
                        ['value' => 'foo'],
                        ['value' => 'bar'],
                    ],
                ],
            ],
            null,
            ['foo', 123]
        );
    }

    function testShouldHandleNodeOptionValidatorErrors()
    {
        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::node(
                    'innerNode',
                    Option::any('option')
                )->validate(static function () {
                    return [
                        new InvalidOptionError('foo'),
                        new InvalidOptionError('bar'),
                    ];
                })->validate(static function () {
                    static::fail('Node validation should stop after first failure');
                }),
                Option::nodeList(
                    'nodeList',
                    Option::any('anotherOption')
                )->validate(static function () {
                    return [
                        new InvalidOptionError('baz'),
                        new InvalidOptionError('qux'),
                    ];
                })->validate(static function () {
                    static::fail('Node list validation should stop after first failure');
                })
            )
        );

        $this->assertResolveFailure(
            $resolver,
            [
                'node' => [
                    'innerNode' => [
                        'option' => 'value',
                    ],
                    'nodeList' => [
                        ['anotherOption' => 'anotherValue'],
                    ],
                ],
            ],
            [
                (new InvalidOptionError('foo'))->at(['node', 'innerNode']),
                (new InvalidOptionError('bar'))->at(['node', 'innerNode']),
                (new InvalidOptionError('baz'))->at(['node', 'nodeList']),
                (new InvalidOptionError('qux'))->at(['node', 'nodeList']),
            ]
        );
    }

    function testShouldValidateNodeOptionsIfThereAreAnyErrors()
    {
        $validator = static function () {
            static::fail('Node validators should not be called if there are errors');
        };

        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::node(
                    'innerNode',
                    Option::string('string')
                )->validate($validator),
                Option::nodeList(
                    'nodeList',
                    Option::string('string')
                )->validate($validator)
            )
        );

        $this->assertResolveFailure(
            $resolver,
            [
                'node' => [
                    'innerNode' => [
                        'string' => 123,
                    ],
                    'nodeList' => [
                        ['string' => 'foo'],
                        ['string' => 456],
                    ],
                ],
            ],
            [
                (new InvalidTypeError('string', false, 123))->at(['node', 'innerNode', 'string']),
                (new InvalidTypeError('string', false, 456))->at(['node', 'nodeList', 1, 'string']),
            ]
        );
    }

    function testShouldNormalizeNodeOptionsBeforeValidation()
    {
        /** @var bool[] $callStatus */
        $callStatus = [
            'nodeNorm' => false,
            'nodeValid' => false,
            'nodeListNorm' => false,
            'nodeListValid' => false,
        ];

        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::any('option')
            )->normalize(static function ($value) use (&$callStatus) {
                static::assertFalse($callStatus['nodeValid'], 'Node normalizers should be called before validators');

                $callStatus['nodeNorm'] = true;

                return $value;
            })->validate(static function () use (&$callStatus) {
                static::assertTrue($callStatus['nodeNorm'], 'Node validators should be called after normalizers');

                $callStatus['nodeValid'] = true;
            }),
            Option::nodeList(
                'nodeList',
                Option::any('anotherOption')
            )->normalize(static function ($value) use (&$callStatus) {
                static::assertFalse($callStatus['nodeListValid'], 'Node normalizers should be called before validators');

                $callStatus['nodeListNorm'] = true;

                return $value;
            })->validate(static function () use (&$callStatus) {
                static::assertTrue($callStatus['nodeListNorm'], 'Node validators should be called after normalizers');

                $callStatus['nodeListValid'] = true;
            })
        );

        $this->assertResolveSuccess(
            $resolver,
            [
                'node' => [
                    'option' => 'value',
                ],
                'nodeList' => [
                    ['anotherOption' => 'value'],
                ],
            ]
        );

        $this->assertSame(
            ['nodeNorm' => true, 'nodeValid' => true, 'nodeListNorm' => true, 'nodeListValid' => true],
            $callStatus
        );
    }

    function testShouldNotValidateNodeOptionsIfThereAreNormalizationErrors()
    {
        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::any('option')
            )->normalize(static function () {
                throw new NormalizerException('', [new InvalidOptionError('foo')]);
            })->validate(static function () {
                static::fail('Validators should not be called if there are normalization errors');
            })
        );

        $this->assertResolveFailure(
            $resolver,
            ['node' => ['option' => 'value']],
            [
                (new InvalidOptionError('foo'))->at(['node']),
            ]
        );
    }

    function testShouldNotValidateNodeListOptionsIfThereAreNormalizationErrors()
    {
        $resolver = $this->createResolver(
            Option::nodeList(
                'nodeList',
                Option::any('option')
            )->normalize(static function () {
                throw new NormalizerException('', [new InvalidOptionError('foo')]);
            })->validate(static function () {
                static::fail('Validators should not be called if there are normalization errors');
            })
        );

        $this->assertResolveFailure(
            $resolver,
            ['nodeList' => [['option' => 'value']]],
            [
                (new InvalidOptionError('foo'))->at(['nodeList']),
            ]
        );
    }

    function testShouldReportNestedErrors()
    {
        $normalizerException = new NormalizerException('test message');
        $this->removeExceptionTrace($normalizerException);

        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::nodeList(
                    'nodeList',
                    Option::any('normalized')->normalize(static function () use ($normalizerException) {
                        throw $normalizerException;
                    }),
                    Option::any('required'),
                    Option::number('number'),
                    Option::any('notEmpty')->notEmpty(),
                    Option::choice('choice', 1, 2, 3),
                    Option::list('intList', 'int'),
                    Option::choiceList('choiceList', 4, 5, 6, 7),
                    Option::list('list', null)->notEmpty()
                ),
                Option::nodeList('nodeList2'),
                Option::nodeList('notEmptyNodeList', Option::any('dummy'))->notEmpty()
            )
        );

        $this->assertResolveFailure(
            $resolver,
            [
                'node' => [
                    'nodeList' => [
                        'not_an_array',
                        [
                            'normalized' => 'dummy',
                            'unknownOption' => 'shouldNotBeHere',
                            'number' => 'not_a_number',
                            'notEmpty' => '',
                            'choice' => 99,
                            'intList' => [1, 2, 'x', 3],
                            'choiceList' => [4, 5, 6, 7, 55],
                            'list' => ['foo', 'bar', 'baz', 0],
                        ],
                    ],
                    'nodeList2' => 'not_an_array2',
                    'notEmptyNodeList' => [],
                ]
            ],
            [
                (new InvalidTypeError('array', false, 'not_an_array'))->at(['node', 'nodeList', 0]),
                (new NormalizerError($normalizerException))->at(['node', 'nodeList', 1, 'normalized']),
                (new MissingOptionError())->at(['node', 'nodeList', 1, 'required']),
                (new InvalidTypeError('number', false, 'not_a_number'))->at(['node', 'nodeList', 1, 'number']),
                (new EmptyValueError(false, ''))->at(['node', 'nodeList', 1, 'notEmpty']),
                (new InvalidChoiceError([1, 2, 3], false, 99))->at(['node', 'nodeList', 1, 'choice']),
                (new InvalidTypeError('int', false, 'x'))->at(['node', 'nodeList', 1, 'intList', 2]),
                (new InvalidChoiceError([4, 5, 6, 7], false, 55))->at(['node', 'nodeList', 1, 'choiceList', 4]),
                (new EmptyValueError(false, 0))->at(['node', 'nodeList', 1, 'list', 3]),
                (new UnknownOptionError())->at(['node', 'nodeList', 1, 'unknownOption']),
                (new InvalidTypeError('array', false, 'not_an_array2'))->at(['node', 'nodeList2']),
                (new EmptyValueError(false, []))->at(['node', 'notEmptyNodeList']),
            ]
        );
    }

    function testShouldNormalizeAndValidateRoot()
    {
        $normalizerCalled = false;
        $validatorCalled = false;

        $resolver = $this->createResolver(
            Option::any('option'),
            Option::any('anotherOption')->default('default')
        );

        $resolver->addNormalizer(static function (Node $node) use (&$normalizerCalled) {
            $node['extraOption'] = 123;
            $normalizerCalled = true;

            return $node;
        });

        $resolver->addValidator(static function (Node $node) use (&$validatorCalled) {
            static::assertSame(123, $node['extraOption']);

            $validatorCalled = true;
        });

        $this->assertResolveSuccess(
            $resolver,
            ['option' => 'value'],
            [
                'option' => 'value',
                'anotherOption' => 'default',
                'extraOption' => 123,
            ]
        );

        $this->assertTrue($normalizerCalled);
        $this->assertTrue($validatorCalled);
    }

    function testShouldHandleRootNormalizationErrors()
    {
        $resolver = $this->createResolver(
            Option::any('option')
        );

        $resolver->addNormalizer(static function (Node $node) {
            throw new NormalizerException('', [new InvalidOptionError('foo')]);
        });

        $resolver->addValidator(static function () {
            static::fail('Validators should not be called if there are normalization errors');
        });

        $this->assertResolveFailure(
            $resolver,
            ['option' => 'value'],
            [new InvalidOptionError('foo')]
        );
    }

    function testShouldHandleRootValidationErrors()
    {
        $resolver = $this->createResolver(
            Option::any('option')
        );

        $resolver->addValidator(static function () {
            return [new InvalidOptionError('foo')];
        });

        $this->assertResolveFailure(
            $resolver,
            ['option' => 'value'],
            [new InvalidOptionError('foo')]
        );
    }

    function testShouldHandleNodeReplacementViaNormalizers()
    {
        $resolver = $this->createResolver(
            Option::node(
                'node',
                Option::any('foo'),
                Option::node(
                    'innerNode',
                    Option::any('bar')
                )->normalize(static function (Node $node) {
                    static::assertSame('bar-value', $node['bar']);

                    return (object) $node->toArray();
                })->validate(static function (\stdClass $node) {
                    static::assertSame('bar-value', $node->bar);
                }),
                Option::nodeList(
                    'innerNodeList',
                    Option::any('baz')
                )->normalize(static function (array $nodeList) {
                    static::assertContainsOnlyInstancesOf(Node::class, $nodeList);
                    static::assertSame('baz-value-1', $nodeList[0]['baz']);
                    static::assertSame('baz-value-2', $nodeList[1]['baz']);

                    return new \ArrayObject(array_map(
                        static function (Node $item) { return (object) $item->toArray(); },
                        $nodeList
                    ));
                })->validate(static function (\ArrayObject $nodeList) {
                    static::assertSame('baz-value-1', $nodeList[0]->baz);
                    static::assertSame('baz-value-2', $nodeList[1]->baz);
                })
            )->normalize(static function (Node $node) {
                static::assertSame('foo-value', $node['foo']);
                static::assertInstanceOf(\stdClass::class, $node['innerNode']);
                static::assertInstanceOf(\ArrayObject::class, $node['innerNodeList']);

                return (object) $node->toArray();
            })->validate(static function (\stdClass $node) {
                static::assertSame('foo-value', $node->foo);
            }),
            Option::nodeList(
                'nodeList',
                Option::any('qux')
            )->normalize(static function (array $nodeList) {
                static::assertContainsOnlyInstancesOf(Node::class, $nodeList);
                static::assertSame('qux-value-1', $nodeList[0]['qux']);
                static::assertSame('qux-value-2', $nodeList[1]['qux']);

                return new \ArrayObject(array_map(
                    static function (Node $item) { return (object) $item->toArray(); },
                    $nodeList
                ));
            })->validate(static function (\ArrayObject $nodeList) {
                static::assertSame('qux-value-1', $nodeList[0]->qux);
                static::assertSame('qux-value-2', $nodeList[1]->qux);
            })
        );

        $resolver->addNormalizer(static function (Node $node) {
            return (object) $node->toArray();
        });

        $resolver->addValidator(static function (\stdClass $node) {
            static::assertObjectHasAttribute('node', $node);
            static::assertObjectHasAttribute('nodeList', $node);
            static::assertInstanceOf(\stdClass::class, $node->node);
            static::assertInstanceOf(\ArrayObject::class, $node->nodeList);
        });

        $this->assertLooselyIdentical(
            (object) [
                'node' => (object) [
                    'foo' => 'foo-value',
                    'innerNode' => (object) [
                        'bar' => 'bar-value',
                    ],
                    'innerNodeList' => new \ArrayObject([
                        (object) ['baz' => 'baz-value-1'],
                        (object) ['baz' => 'baz-value-2'],
                    ]),
                ],
                'nodeList' => new \ArrayObject([
                    (object) ['qux' => 'qux-value-1'],
                    (object) ['qux' => 'qux-value-2'],
                ]),
            ],
            $resolver->resolve([
                'node' => [
                    'foo' => 'foo-value',
                    'innerNode' => [
                        'bar' => 'bar-value',
                    ],
                    'innerNodeList' => [
                        ['baz' => 'baz-value-1'],
                        ['baz' => 'baz-value-2'],
                    ],
                ],
                'nodeList' => [
                    ['qux' => 'qux-value-1'],
                    ['qux' => 'qux-value-2'],
                ],
            ])
        );
    }

    function provideTypeInfos()
    {
        return [
            'null' => [[
                'type' => null,
                'validValues' => [
                    true,
                    false,
                    123,
                    3.14,
                    'foo',
                    [1, 2, 3],
                    new \ArrayObject([4, 5, 6]),
                    fopen(__FILE__, 'r'),
                ],
                'invalidValues' => [],
                'emptyValues' => ['', 0, 0.0, '0', false, []],
            ],],

            'bool' => [[
                'type' => 'bool',
                'validValues' => [true, false],
                'invalidValues' => ['1', '0', 1, 0, 'not_a_bool'],
                'emptyValues' => [false],
            ],],

            'int' => [[
                'type' => 'int',
                'validValues' => [0, -1, 1, -123456, 123456],
                'invalidValues' => [0.0 -1.2, 1.2, '123', 'not_an_int'],
                'emptyValues' => [0],
            ],],

            'float' => [[
                'type' => 'float',
                'validValues' => [0.0, -1.0, 1.0, -3.14, 3.14, -123456.0, 123456.0],
                'invalidValues' => [0, -1, 1, -123456, 123456, 'not_a_float'],
                'emptyValues' => [0.0],
            ],],

            'number' => [[
                'type' => 'number',
                'validValues' => [
                    0, -1, 1, -123456, 123456,
                    0.0, -1.0, 1.0, -3.14, 3.14, -123456.0, 123456.0,
                ],
                'invalidValues' => [
                    '0', '-1', '1', '-123456', '+123465', '1e3',
                    '0.0', '-1.0', '1.1', '-123456.0', '+123456.0',
                    'not_int_or_float',
                ],
                'emptyValues' => [0, 0.0],
            ],],

            'numeric' => [[
                'type' => 'numeric',
                'validValues' => [
                    0, -1, 1, -123456, 123456,
                    0.0, -1.0, 1.0, -3.14, 3.14, -123456.0, 123456.0,
                    '0', '-1', '1', '-123456', '+123465', '1e3',
                    '0.0', '-1.0', '1.1', '-123456.0', '+123456.0',
                ],
                'invalidValues' => ['', 'not_a_number'],
                'emptyValues' => [0, 0.0, '0'],
            ],],

            'string' => [[
                'type' => 'string',
                'validValues' => ['', 'foo'],
                'invalidValues' => [132, 3.14, []],
                'emptyValues' => [''],
            ],],

            'array' => [[
                'type' => 'array',
                'validValues' => [[], [1, 2, 3], ['foo' => 'bar']],
                'invalidValues' => [123, 'not_an_array'],
                'emptyValues' => [[]],
            ],],

            'iterable' => [[
                'type' => 'iterable',
                'validValues' => [
                    [],
                    [1, 2, 3],
                    new \ArrayIterator(),
                    new \ArrayIterator([4, 5, 6]),
                ],
                'invalidValues' => [
                    new \stdClass(),
                    'not_iterable',
                ],
                'emptyValues' => [[]],
            ],],

            'object' => [[
                'type' => 'object',
                'validValues' => [new \stdClass(), new \ArrayObject()],
                'invalidValues' => [123, 'not_an_object'],
                'emptyValues' => [],
            ],],

            'resource' => [[
                'type' => 'resource',
                'validValues' => [fopen(__FILE__, 'r'), stream_context_create()],
                'invalidValues' => [123, 'not_a_resource'],
                'emptyValues' => [],
            ],],

            'scalar' => [[
                'type' => 'scalar',
                'validValues' => [
                    123,
                    0,
                    3.14,
                    0.0,
                    'foo',
                    '',
                    true,
                    false,
                ],
                'invalidValues' => [
                    [],
                    new \stdClass(),
                    fopen(__FILE__, 'r'),
                ],
                'emptyValues' => ['', 0, 0.0, '0', false],
            ],],

            'callable' => [[
                'type' => 'callable',
                'validValues' => [
                    'trim',
                    [__CLASS__, 'any'],
                    [$this, 'any'],
                    static function () {},
                ],
                'invalidValues' => [
                    __NAMESPACE__ . '\\____nonexistentFunction',
                    [Resolver::class, '____nonexistentMethod'],
                    123,
                ],
                'emptyValues' => [],
            ],],

            'class name' => [[
                'type' => \stdClass::class,
                'validValues' => [
                    new \stdClass(),
                    new class extends \stdClass {},
                ],
                'invalidValues' => [
                    new \ArrayObject(),
                    123,
                ],
                'emptyValues' => [],
            ],],
        ];
    }

    private function createResolver(OptionDefinition ...$options): Resolver
    {
        $resolver = new Resolver();
        $resolver->addOption(...$options);

        return $resolver;
    }

    private function assertResolveSuccess(
        Resolver $resolver,
        array $data,
        ?array $expectedResult = null,
        array $context = [],
        string $message = ''
    ) {
        try {
            $node = $resolver->resolve($data, $context);

            $this->assertSame([], $node->getPath());
            $this->assertLooselyIdentical($expectedResult ?? $data, $node->toArray(), false, $message);
        } catch (ResolverException $e) {
            $this->fail(sprintf("ResolveException was thrown%s\n\n%s", ($message !== '' ? " - {$message}" : ''), $e->getMessage()));
        }
    }

    private function assertResolveFailure(Resolver $resolver, $data, array $expectedErrors, string $message = ''): void
    {
        Error::sort($expectedErrors);

        try {
            $resolver->resolve($data);

            $this->fail('ResolverException was not thrown' . ($message !== '' ? " - {$message}" : ''));
        } catch (ResolverException $e) {
            $actualErrors = $e->getErrors();
            Error::sort($actualErrors);

            try {
                $this->assertLooselyIdentical($expectedErrors, $actualErrors, false, $message);
            } catch (AssertionFailedError $e) {
                throw new ExpectationFailedException(
                    sprintf(
                        "Expected errors do not match%s\n\n> Expected errors:\n\n%s\n\n> Actual errors:\n\n%s",
                        $message !== '' ? " ({$message})" : '',
                        implode("\n", $expectedErrors),
                        implode("\n", $actualErrors)
                    ),
                    null,
                    $e
                );
            }
        }
    }

    /**
     * Remove exception trace so PHPUnit's differ isn't broken by recursion
     */
    private function removeExceptionTrace(\Exception $e): void
    {
        $trace = new \ReflectionProperty(\Exception::class, 'trace');
        $trace->setAccessible(true);
        $trace->setValue($e, []);
    }
}
