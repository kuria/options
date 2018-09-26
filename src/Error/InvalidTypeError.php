<?php declare(strict_types=1);

namespace Kuria\Options\Error;

class InvalidTypeError extends Error
{
    /** @var string|null */
    private $expectedType;

    /** @var bool */
    private $nullable;

    /** @var mixed */
    private $actualValue;

    function __construct(?string $expectedType, bool $nullable, $actualValue)
    {
        $this->expectedType = $expectedType;
        $this->nullable = $nullable;
        $this->actualValue = $actualValue;
    }

    function getMessage(): string
    {
        if ($this->expectedType === null) {
            return sprintf('a %s value is not allowed', static::dump($this->actualValue));
        }

        $message = $this->expectedType;

        if ($this->nullable) {
            $message .= ' or NULL';
        }

        $message .= sprintf(' expected, but got %s instead', static::dump($this->actualValue));

        return $message;
    }

    function getExpectedType(): ?string
    {
        return $this->expectedType;
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
