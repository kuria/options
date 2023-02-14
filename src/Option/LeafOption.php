<?php declare(strict_types=1);

namespace Kuria\Options\Option;

use Kuria\Options\Node;

/**
 * Leaf option
 *
 * The public properties are read-only. Use the Option factory class to build instances.
 *
 * @see \Kuria\Options\Option
 */
class LeafOption extends OptionDefinition
{
    /** @var string|null read-only */
    public $type;

    /** @var array|null read-only */
    public $choices;

    /** @var bool|null */
    private $defaultIsLazy;

    function __construct(string $name, ?string $type = null)
    {
        parent::__construct($name);

        $this->type = $type;
    }

    function required(): OptionDefinition
    {
        $this->defaultIsLazy = null;

        return parent::required();
    }

    /**
     * Specify a default value
     *
     * It can be of any type or a closure with the following signature:
     *
     *      function (Node $node) { return <anything>; }
     *
     * @return $this
     */
    function default($default): self
    {
        $this->required = false;
        $this->default = $default;
        $this->defaultIsLazy = null;
        $this->nullable = $default === null;

        return $this;
    }

    /**
     * See if the specified default value is a closure accepting a Node instance
     */
    function defaultIsLazy(): bool
    {
        return $this->defaultIsLazy ?? (
            $this->defaultIsLazy = $this->default instanceof \Closure
                // must have at least one required parameter
                && (new \ReflectionFunction($this->default))->getNumberOfRequiredParameters() >= 1
                // the parameter must have a typehint
                && ($firstParamType = (new \ReflectionParameter($this->default, 0))->getType()) instanceof \ReflectionNamedType
                // the typehint must be the Node class
                && $firstParamType->getName() === Node::class
            );
    }
}
