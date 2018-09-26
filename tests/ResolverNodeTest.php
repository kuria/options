<?php declare(strict_types=1);

namespace Kuria\Options;

use Kuria\DevMeta\Test;
use Kuria\Options\Error\InvalidOptionError;
use Kuria\Options\Option\NodeOption;

class ResolverNodeTest extends Test
{
    function testShouldCreateResolverNode()
    {
        $option = new NodeOption('dummy', []);

        $ref = [
            'baz' => 'qux',
            'quux' => 123,
        ];

        $errorsRef = [];

        $resolverNode = new ResolverNode($option, ['foo', 'bar'], $ref, $errorsRef);
        $resolverNode->ref['reftest'] = true;

        $this->assertSame($option, $resolverNode->option);
        $this->assertSame($ref, $resolverNode->ref);
        $this->assertSame(['foo', 'bar'], $resolverNode->path);
    }

    function testShouldAddErrors()
    {
        $ref = [];
        $errorsRef = [];

        $errorA = new InvalidOptionError('a');
        $errorB = new InvalidOptionError('b');
        $errorC = new InvalidOptionError('c');
        $errorD = new InvalidOptionError('d');
        $errorE = new InvalidOptionError('e');
        $errorF = new InvalidOptionError('f');

        $resolverNode = new ResolverNode(new NodeOption('dummy', []), ['foo'], $ref, $errorsRef);

        $resolverNode->addError($errorA);
        $resolverNode->addError($errorB, 'bar', 'baz');
        $resolverNode->addErrors([$errorC, $errorD]);
        $resolverNode->addErrors([$errorE, $errorF], 'qux');

        $this->assertSame([$errorA, $errorB, $errorC, $errorD, $errorE, $errorF], $errorsRef);
        $this->assertSame(['foo'], $errorA->getPath());
        $this->assertSame(['foo', 'bar', 'baz'], $errorB->getPath());
        $this->assertSame(['foo'], $errorC->getPath());
        $this->assertSame(['foo'], $errorD->getPath());
        $this->assertSame(['foo', 'qux'], $errorE->getPath());
        $this->assertSame(['foo', 'qux'], $errorF->getPath());
    }

    /**
     * @dataProvider provideChildPaths
     */
    function testShouldGetChildPath(array $path, array $arguments, array $expectedResult)
    {
        $ref = [];
        $errorsRef = [];
        $resolverNode = new ResolverNode(new NodeOption('dummy', []), $path, $ref, $errorsRef);

        $this->assertSame($expectedResult, $resolverNode->getChildPath(...$arguments));
    }

    function provideChildPaths()
    {
        return [
            // path, arguments, expectedResult
            [[], [], []],
            [['foo'], [], ['foo']],
            [['foo', 'bar'], ['baz', 'qux'], ['foo', 'bar', 'baz', 'qux']],
        ];
    }
}
