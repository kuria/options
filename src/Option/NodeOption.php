<?php declare(strict_types=1);

namespace Kuria\Options\Option;

/**
 * Node option
 *
 * The public properties are read-only. Use the Option factory class to build instances.
 *
 * @see \Kuria\Options\Option
 */
class NodeOption extends OptionDefinition
{
    /** @var array|null read-only */
    public $default;

    /** @var OptionDefinition[] name-indexed, read-only */
    public $children;

    /**
     * @param OptionDefinition[] $children name-indexed
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
