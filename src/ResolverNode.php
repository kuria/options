<?php declare(strict_types=1);

namespace Kuria\Options;

use Kuria\Options\Error\Error;
use Kuria\Options\Option\NodeOption;

class ResolverNode
{
    /** @var NodeOption */
    public $option;

    /** @var array */
    public $path;

    /** @var mixed */
    public $ref;

    /** @var bool */
    public $isListItem;

    /** @var Error[] */
    private $errorsRef;

    function __construct(NodeOption $option, array $path, &$ref, array &$errorsRef, bool $isListItem = false)
    {
        $this->option = $option;
        $this->path = $path;
        $this->ref = &$ref;
        $this->isListItem = $isListItem;
        $this->errorsRef = &$errorsRef;
    }

    function addError(Error $error, ...$childPath): void
    {
        $this->errorsRef[] = $error->at($this->getChildPath(...$childPath));
    }

    function addErrors(array $errors, ...$childPath): void
    {
        $errorPath = $this->getChildPath(...$childPath);

        foreach ($errors as $error) {
            $this->errorsRef[] = $error->at($errorPath);
        }
    }

    function getChildPath(...$childPath): array
    {
        return array_merge($this->path, $childPath);
    }
}
