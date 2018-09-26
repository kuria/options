<?php declare(strict_types=1);

namespace Kuria\Options\Exception;

class CircularDependencyException extends \LogicException implements ExceptionInterface
{
}
