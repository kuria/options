<?php declare(strict_types=1);

namespace Kuria\Options\Helper;

use Kuria\DevMeta\Test;

class NodeHelperTest extends Test
{
    /**
     * @dataProvider providePaths
     */
    function testShouldFormatPath(array $path, string $expectedResult)
    {
        $this->assertSame($expectedResult, NodeHelper::formatPath($path));
    }

    function providePaths()
    {
        return [
            // path, expectedResult
            [[], ''],
            [['foo'], 'foo'],
            [[1], '1'],
            [['foo', 'bar', 123, 456, 'baz'], 'foo[bar][123][456][baz]'],
        ];
    }
}
