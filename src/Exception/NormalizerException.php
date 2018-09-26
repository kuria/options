<?php declare(strict_types=1);

namespace Kuria\Options\Exception;

use Kuria\Options\Error\Error;
use Kuria\Options\Error\NormalizerError;

class NormalizerException extends \UnexpectedValueException implements ExceptionInterface
{
    /** @var Error[] */
    private $errors;

    /**
     * @param Error[] $errors
     */
    function __construct(string $message, array $errors = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->errors = $errors;
    }

    /**
     * @return Error[]
     */
    function getErrors(): array
    {
        return $this->errors ?: [new NormalizerError($this)];
    }
}
