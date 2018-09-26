<?php declare(strict_types=1);

namespace Kuria\Options\Option;

use Kuria\DevMeta\Test;
use Kuria\Options\Traits\OptionAssertionTrait;

abstract class OptionTest extends Test
{
    use OptionAssertionTrait;

    abstract protected function createOption(string $name): Option;

    /**
     * @dataProvider provideFluentMethodCalls
     */
    function testShouldConfigureOptionViaFluentInterface(array $methodCalls, array $expectedFinalProps)
    {
        $option = $this->createOption('foo_bar');

        foreach ($methodCalls as $methodCall) {
            $this->assertSame($option, $option->{$methodCall[0]}(...array_slice($methodCall, 1)));
        }

        $this->assertOption(['name' => 'foo_bar'] + $expectedFinalProps, $option);
    }

    function provideFluentMethodCalls()
    {
        $callbackA = static function () {};
        $callbackB = static function () {};

        return [
            // methodCalls, expectedFinalProps
            'initial' => [
                [],
                [],
            ],

            'required' => [
                [
                    ['required'],
                ],
                [
                    'required' => true,
                    'default' => null,
                ],
            ],

            'nullable' => [
                [
                    ['nullable'],
                ],
                [
                    'nullable' => true,
                ],
            ],

            'nullable + notNullable' => [
                [
                    ['nullable'],
                    ['notNullable'],
                ],
                [
                    'nullable' => false,
                ],
            ],

            'allowEmpty' => [
                [
                    ['allowEmpty'],
                ],
                [
                    'allowEmpty' => true,
                ],
            ],

            'allowEmpty + notEmpty' => [
                [
                    ['allowEmpty'],
                    ['notEmpty'],
                ],
                [
                    'allowEmpty' => false,
                ],
            ],

            'validate' => [
                [
                    ['validate', $callbackA],
                ],
                [
                    'validators' => [$callbackA],
                ],
            ],

            'multiple validators' => [
                [
                    ['validate', $callbackA],
                    ['validate', $callbackB],
                ],
                [
                    'validators' => [$callbackA, $callbackB],
                ],
            ],
        ];
    }
}
