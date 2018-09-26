<?php declare(strict_types=1);

namespace Kuria\Options\Error;

use Kuria\Options\Exception\NormalizerException;

class NormalizerError extends Error
{
    /** @var NormalizerException */
    private $normalizerException;

    function __construct(NormalizerException $normalizerException)
    {
        $this->normalizerException = $normalizerException;
    }

    function getNormalizerException(): NormalizerException
    {
        return $this->normalizerException;
    }

    function getMessage(): string
    {
        return sprintf('normalization failed - %s', $this->normalizerException->getMessage());
    }
}
