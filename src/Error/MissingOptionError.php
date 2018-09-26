<?php declare(strict_types=1);

namespace Kuria\Options\Error;

class MissingOptionError extends Error
{
    function getMessage(): string
    {
        return 'this option is required';
    }
}
