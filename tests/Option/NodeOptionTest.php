<?php declare(strict_types=1);

namespace Kuria\Options\Option;

class NodeOptionTest extends OptionTest
{
    protected function createOption(string $name): Option
    {
        return new NodeOption($name, []);
    }

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
        $cases = parent::provideFluentMethodCalls();

        $cases += [
            // methodCalls, expectedFinalProps
            'default' => [
                [
                    ['default', ['option' => 'value']],
                ],
                [
                    'required' => false,
                    'default' => ['option' => 'value'],
                ],
            ],

            'default null' => [
                [
                    ['default', null],
                ],
                [
                    'required' => false,
                    'nullable' => true,
                    'default' => null,
                ],
            ],

            'default null + not null' => [
                [
                    ['default', null],
                    ['default', ['option' => 'value']],
                ],
                [
                    'required' => false,
                    'nullable' => false,
                    'default' => ['option' => 'value'],
                ],
            ],

            'required + default' => [
                [
                    ['required'],
                    ['default', ['option' => 'value']],
                ],
                [
                    'required' => false,
                    'default' => ['option' => 'value'],
                ],
            ],

            'default + required' => [
                [
                    ['default', ['option' => 'value']],
                    ['required'],
                ],
                [
                    'required' => true,
                    'default' => null,
                ],
            ],

            'default + nullable' => [
                [
                    ['default', ['option' => 'value']],
                    ['nullable'],
                ],
                [
                    'required' => false,
                    'nullable' => true,
                    'default' => ['option' => 'value'],
                ],
            ],
        ];

        return $cases;
    }
}
