<?php declare(strict_types=1);

namespace Kuria\Options\Error;

class InvalidOptionError extends Error
{
    /** @var string */
    private $message;

    function __construct(string $message)
    {
        $this->message = $message;
    }

    function getMessage(): string
    {
        return $this->message;
    }
}
