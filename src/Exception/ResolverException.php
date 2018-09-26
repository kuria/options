<?php declare(strict_types=1);

namespace Kuria\Options\Exception;

use Kuria\Options\Error\Error;

class ResolverException extends \InvalidArgumentException implements ExceptionInterface
{
    /** @var Error[] */
    private $errors;

    /**
     * @param Error[] $errors
     */
    function __construct(array $errors, int $code = 0, ?\Throwable $previous = null)
    {
        $message = "Failed to resolve options due to following errors:\n";

        $errorNumber = 1;

        foreach ($errors as $error) {
            $message .= "\n{$errorNumber}) {$error}";
            ++$errorNumber;
        }

        parent::__construct($message, $code, $previous);

        $this->errors = $errors;
    }

    /**
     * @return Error[]
     */
    function getErrors(): array
    {
        return $this->errors;
    }
}
