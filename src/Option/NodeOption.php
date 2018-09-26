<?php declare(strict_types=1);

namespace Kuria\Options\Option;

/**
 * Node option
 *
 * The public properties are read-only. Use OptionFactory to build the instances.
 *
 * @see \Kuria\Options\OptionFactory
 */
class NodeOption extends Option
{
    /** @var array|null read-only */
    public $default;

    /** @var Option[] name-indexed, read-only */
    public $children;

    /**
     * @param Option[] $children name-indexed
     */
    function __construct(string $name, array $children)
    {
        parent::__construct($name);

        $this->required = false;
        $this->children = $children;
        $this->default = [];
    }

    /**
     * Specify a default value
     *
     * @return $this
     */
    function default(?array $default): self
    {
        $this->required = false;
        $this->default = $default;
        $this->nullable = $default === null;

        return $this;
    }
}
