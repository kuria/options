<?php declare(strict_types=1);

namespace Kuria\Options\Option;

use Kuria\Options\Node;

/**
 * Leaf option
 *
 * The public properties are read-only. Use OptionFactory to build the instances.
 *
 * @see \Kuria\Options\OptionFactory
 */
class LeafOption extends Option
{
    /** @var string|null read-only */
    public $type;

    /** @var array|null read-only */
    public $choices;

    /** @var callable[]|null read-only */
    public $normalizers;

    /** @var bool|null */
    private $defaultIsLazy;

    function __construct(string $name, ?string $type = null)
    {
        parent::__construct($name);

        $this->type = $type;
    }

    function required(): Option
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
                // must have a single required parameter
                && (new \ReflectionFunction($this->default))->getNumberOfRequiredParameters() === 1
                // the parameter must have a typehint
                && ($firstParamType = (new \ReflectionParameter($this->default, 0))->getType()) instanceof \ReflectionNamedType
                // the typehint must be the Node class
                && $firstParamType->getName() === Node::class
            );
    }

    /**
     * Append a normalizer
     *
     * Callback signature: ($value): mixed
     *
     * It may throw NormalizerException on failure.
     *
     * @see \Kuria\Options\Exception\NormalizerException
     *
     * @return $this
     */
    function normalize(callable $normalizer): self
    {
        $this->normalizers[] = $normalizer;

        return $this;
    }
}
