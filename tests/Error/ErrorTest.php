<?php declare(strict_types=1);

namespace Kuria\Options\Error;

use Kuria\DevMeta\Test;
use Kuria\Options\Exception\NormalizerException;
use PHPUnit\Framework\MockObject\MockObject;

class ErrorTest extends Test
{
    /**
     * @dataProvider provideErrors
     */
    function testShouldCreateError(string $className, array $arguments, array $expectedMethodResults)
    {
        /** @var Error $error */
        $error = new $className(...$arguments);

        foreach ($expectedMethodResults as $method => $expectedResult) {
            $this->assertSame($expectedResult, $error->{$method}(), "{$method}() should return the expected value");
        }
    }

    function provideErrors()
    {
        $object = new \stdClass();

        return [
            // className, arguments, expectedMethodResults
            [
                InvalidOptionError::class,
                ['this is a message'],
                [
                    'getMessage' => 'this is a message',
                ],
            ],
            [
                EmptyValueError::class,
                [false, ''],
                [
                    'getMessage' => 'a non-empty value is expected, but got "" instead',
                    'isNullable' => false,
                    'getActualValue' => '',
                ],
            ],
            [
                EmptyValueError::class,
                [true, []],
                [
                    'getMessage' => 'a non-empty value or NULL is expected, but got array[0] instead',
                    'isNullable' => true,
                    'getActualValue' => [],
                ],
            ],
            [
                InvalidChoiceError::class,
                [['lorem', 'ipsum', 'dolor'], false, 'sit'],
                [
                    'getMessage' => '"lorem", "ipsum" or "dolor" is expected, but got "sit" instead',
                    'getChoices' => ['lorem', 'ipsum', 'dolor'],
                    'isNullable' => false,
                    'getActualValue' => 'sit',
                ],
            ],
            [
                InvalidChoiceError::class,
                [[1, 2], true, $object],
                [
                    'getMessage' => '1, 2 or NULL is expected, but got object(stdClass) instead',
                    'getChoices' => [1, 2],
                    'isNullable' => true,
                    'getActualValue' => $object,
                ],
            ],
            [
                InvalidTypeError::class,
                ['int', true, $object],
                [
                    'getMessage' => 'int or NULL expected, but got object(stdClass) instead',
                    'getExpectedType' => 'int',
                    'isNullable' => true,
                    'getActualValue' => $object,
                ],
            ],
            [
                InvalidTypeError::class,
                [null, false, null],
                [
                    'getMessage' => 'a NULL value is not allowed',
                    'getExpectedType' => null,
                    'isNullable' => false,
                    'getActualValue' => null,
                ],
            ],
            [
                MissingOptionError::class,
                [],
                [
                    'getMessage' => 'this option is required',
                ],
            ],
            [
                NormalizerError::class,
                [$normalizerEx = new NormalizerException('Test exception')],
                [
                    'getMessage' => 'normalization failed - Test exception',
                    'getNormalizerException' => $normalizerEx,
                ],
            ],
            [
                UnknownOptionError::class,
                [],
                [
                    'getMessage' => 'unknown option',
                ],
            ],
        ];
    }

    function testShouldSetAndGetPath()
    {
        /** @var Error|MockObject $error */
        $error = $this->getMockForAbstractClass(Error::class);

        $this->assertSame([], $error->getPath());
        $this->assertSame('', $error->getFormattedPath());

        $this->assertSame($error, $error->at(['foo']));
        $this->assertSame(['foo'], $error->getPath());
        $this->assertSame('foo', $error->getFormattedPath());

        $this->assertSame($error, $error->at(['foo', 'bar', 123, 'baz']));
        $this->assertSame(['foo', 'bar', 123, 'baz'], $error->getPath());
        $this->assertSame('foo[bar][123][baz]', $error->getFormattedPath());
    }

    /**
     * @dataProvider provideErrorsForStringConversion
     */
    function testShouldConvertToString(Error $error, string $expectedResult)
    {
        $this->assertSame($expectedResult, (string) $error);
    }

    function provideErrorsForStringConversion()
    {
        return [
            // error, expectedResult
            'without path' => [
                (function () {
                    $error = $this->getMockForAbstractClass(Error::class);
                    $error->method('getMessage')
                        ->willReturn('foo');

                    return $error;
                })(),
                'foo',
            ],

            'with path' => [
                (function () {
                    /** @var Error|MockObject $error */
                    $error = $this->getMockForAbstractClass(Error::class);
                    $error->method('getMessage')
                        ->willReturn('bar');

                    return $error->at(['baz', 123]);
                })(),
                'baz[123]: bar',
            ],
        ];
    }

    function testShouldSortErrorsByPath()
    {
        $errors = [
            (new InvalidOptionError(''))->at(['x', 'y', 'z']),
            (new InvalidOptionError(''))->at(['a', 'b', 'c']),
            (new InvalidOptionError(''))->at(['a']),
            (new InvalidOptionError(''))->at([0, 1, 2]),
        ];

        $expectedSortedErrors = [
            $errors[3],
            $errors[2],
            $errors[1],
            $errors[0],
        ];

        Error::sort($errors);

        $this->assertSame($errors, $expectedSortedErrors);
    }
}
