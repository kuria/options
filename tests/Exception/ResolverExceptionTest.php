<?php declare(strict_types=1);

namespace Kuria\Options\Exception;

use Kuria\DevMeta\Test;
use Kuria\Options\Error\InvalidOptionError;

class ResolverExceptionTest extends Test
{
    function testShouldCreateException()
    {
        $errors = [
            new InvalidOptionError('foo'),
            (new InvalidOptionError('bar'))->at(['lorem']),
            (new InvalidOptionError('baz'))->at(['ipsum', 123]),
        ];

        $previous = new \Exception();

        $expectedMessage = <<<'MESSAGE'
Failed to resolve options due to following errors:

1) foo
2) lorem: bar
3) ipsum[123]: baz
MESSAGE;

        $exception = new ResolverException($errors, 123, $previous);

        $this->assertSame($expectedMessage, $exception->getMessage());
        $this->assertSame($errors, $exception->getErrors());
        $this->assertSame(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
