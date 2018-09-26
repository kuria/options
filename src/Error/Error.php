<?php declare(strict_types=1);

namespace Kuria\Options\Error;

use Kuria\Options\Helper\NodeHelper;
use Kuria\Debug\Dumper;

abstract class Error
{
    private $path = [];

    function __toString()
    {
        if ($this->path) {
            return "{$this->getFormattedPath()}: {$this->getMessage()}";
        }

        return $this->getMessage();
    }

    /**
     * Define the error path
     *
     * @return $this
     */
    function at(array $path): self
    {
        $this->path = $path;

        return $this;
    }

    function getPath(): array
    {
        return $this->path;
    }

    function getFormattedPath(): string
    {
        return NodeHelper::formatPath($this->path);
    }

    abstract function getMessage(): string;

    /**
     * Sort a list of errors by their paths
     *
     * @param self[] $errors
     */
    static function sort(array &$errors): void
    {
        usort($errors, function (Error $a, Error $b) {
            return strnatcmp($a->getFormattedPath(), $b->getFormattedPath());
        });
    }

    /**
     * Dump arbitrary value for use in the message
     */
    protected static function dump($value): string
    {
        return Dumper::dump($value, 1, 32);
    }
}
