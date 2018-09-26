<?php declare(strict_types=1);

namespace Kuria\Options\Error;

class UnknownOptionError extends Error
{
    function getMessage(): string
    {
        return 'unknown option';
    }
}
