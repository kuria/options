<?php declare(strict_types=1);

namespace Kuria\Options\Exception;

use Kuria\DevMeta\Test;
use Kuria\Options\Error\InvalidOptionError;
use Kuria\Options\Error\NormalizerError;

class NormalizerExceptionTest extends Test
{
    function testShouldCreateException()
    {
        $errors = [new InvalidOptionError('foo'), new InvalidOptionError('bar')];
        $previous = new \Exception();
        $exception = new NormalizerException('Foo bar', $errors, 123, $previous);

        $this->assertSame('Foo bar', $exception->getMessage());
        $this->assertSame($errors, $exception->getErrors());
        $this->assertSame(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    function testShouldReturnNormalizerErrorIfNoneGiven()
    {
        $exception = new NormalizerException('Foo bar');

        $this->assertSame('Foo bar', $exception->getMessage());
        $this->assertLooselyIdentical([new NormalizerError($exception)], $exception->getErrors());
    }
}
