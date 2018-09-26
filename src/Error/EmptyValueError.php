<?php declare(strict_types=1);

namespace Kuria\Options\Error;

class EmptyValueError extends Error
{
    /** @var bool */
    private $nullable;

    /** @var mixed */
    private $actualValue;

    function __construct(bool $nullable, $actualValue)
    {
        $this->nullable = $nullable;
        $this->actualValue = $actualValue;
    }

    function getMessage(): string
    {
        $message = 'a non-empty value';

        if ($this->nullable) {
            $message .= ' or NULL';
        }

        $message .= sprintf(' is expected, but got %s instead', static::dump($this->actualValue));

        return $message;
    }

    function isNullable(): bool
    {
        return $this->nullable;
    }

    function getActualValue()
    {
        return $this->actualValue;
    }
}
