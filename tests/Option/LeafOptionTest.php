<?php declare(strict_types=1);

namespace Kuria\Options\Option;

use Kuria\Options\Node;

class LeafOptionTest extends OptionTest
{
    protected function createOption(string $name): OptionDefinition
    {
        return new LeafOption($name);
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
                    ['default', 'value'],
                ],
                [
                    'required' => false,
                    'default' => 'value',
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
                    ['default', 'value'],
                ],
                [
                    'required' => false,
                    'nullable' => false,
                    'default' => 'value',
                ],
            ],

            'required + default' => [
                [
                    ['required'],
                    ['default', 'value'],
                ],
                [
                    'required' => false,
                    'default' => 'value',
                ],
            ],

            'default + required' => [
                [
                    ['default', 'value'],
                    ['required'],
                ],
                [
                    'required' => true,
                    'default' => null,
                ],
            ],

            'default + nullable' => [
                [
                    ['default', 'value'],
                    ['nullable'],
                ],
                [
                    'required' => false,
                    'nullable' => true,
                    'default' => 'value',
                ],
            ],
        ];

        return $cases;
    }

    /**
     * @dataProvider provideDefaultsForLazyDetection
     */
    function testShouldDetectLazyDefault($default, bool $expectedIsLazy)
    {
        $option = new LeafOption('dummy');
        $option->default($default);

        $this->assertSame($expectedIsLazy, $option->defaultIsLazy());
    }

    function provideDefaultsForLazyDetection()
    {
        return [
            // default, expectedIsLazy
            'lazy' => [static function (Node $node) {}, true],
            'lazy with extra optional param' => [static function (Node $node, $anotherParam = null) {}, true],
            'closure with no params' => [static function () {}, false],
            'closure with not typehinted param' => [static function ($foo) {}, false],
            'closure with incompatible param' => [static function (array $bar) {}, false],
            'closure with incompatible class' => [static function (\stdClass $baz) {}, false],
            'string' => ['qux', false],
        ];
    }

    function testShouldDetectLazyDefaultWhenDefaultChanges()
    {
        $option = new LeafOption('dummy');

        $this->assertFalse($option->defaultIsLazy());

        $option->default(static function (Node $node) {});
        $this->assertTrue($option->defaultIsLazy());

        $option->required();
        $this->assertFalse($option->defaultIsLazy());

        $option->default('foo');
        $this->assertFalse($option->defaultIsLazy());
    }
}
