<?php declare(strict_types=1);

namespace Kuria\Options;

use Kuria\Options\Option\OptionDefinition;
use Kuria\Options\Option\NodeOption;
use Kuria\Options\Option\LeafOption;

abstract class Option
{
    /**
     * Create a mixed option
     *
     * It accepts all value types. NULL is accepted only if the option is nullable.
     *
     * @return LeafOption
     */
    static function any(string $name): LeafOption
    {
        return new LeafOption($name, null);
    }

    /**
     * Create a boolean option
     *
     * @return LeafOption
     */
    static function bool(string $name): LeafOption
    {
        return new LeafOption($name, 'bool');
    }

    /**
     * Create an integer option
     *
     * @return LeafOption
     */
    static function int(string $name): LeafOption
    {
        return new LeafOption($name, 'int');
    }

    /**
     * Create a float option
     *
     * @return LeafOption
     */
    static function float(string $name): LeafOption
    {
        return new LeafOption($name, 'float');
    }

    /**
     * Create a number option
     *
     * It accepts integers and floats.
     *
     * @return LeafOption
     */
    static function number(string $name): LeafOption
    {
        return new LeafOption($name, 'number');
    }

    /**
     * Create a numeric option
     *
     * It accepts integers, floats and numeric strings.
     *
     * @return LeafOption
     */
    static function numeric(string $name): LeafOption
    {
        return new LeafOption($name, 'numeric');
    }

    /**
     * Create a string option
     *
     * @return LeafOption
     */
    static function string(string $name): LeafOption
    {
        return new LeafOption($name, 'string');
    }

    /**
     * Create an array option
     *
     * It accepts an array. The individual values are not validated.
     *
     * @return LeafOption
     */
    static function array(string $name): LeafOption
    {
        return new LeafOption($name, 'array');
    }

    /**
     * Create a list option
     *
     * It accepts an array with values of the specified type.
     * Each value is validated and must not be NULL.
     *
     * @return LeafOption
     */
    static function list(string $name, ?string $type): LeafOption
    {
        $option = new LeafOption($name, $type);
        $option->list = true;

        return $option;
    }

    /**
     * Create an iterable option
     *
     * It accepts both arrays and \Traversable instances.
     * The individual values are not validated.
     *
     * @return LeafOption
     */
    static function iterable(string $name): LeafOption
    {
        return new LeafOption($name, 'iterable');
    }

    /**
     * Create an object option
     *
     * If $className is specified, only instances of the given class
     * or interface (or their descendants) will be accepted.
     *
     * @return LeafOption
     */
    static function object(string $name, ?string $className = null): LeafOption
    {
        return new LeafOption($name, $className ?? 'object');
    }

    /**
     * Create a resource option
     *
     * @return LeafOption
     */
    static function resource(string $name): LeafOption
    {
        return new LeafOption($name, 'resource');
    }

    /**
     * Create a scalar option
     *
     * @return LeafOption
     */
    static function scalar(string $name): LeafOption
    {
        return new LeafOption($name, 'scalar');
    }

    /**
     * Create a choice option
     *
     * It accepts one of the listed values. If the option is nullable, NULL is accepted as well.
     *
     * @return LeafOption
     */
    static function choice(string $name, ...$choices): LeafOption
    {
        $option = new LeafOption($name);
        $option->choices = $choices;

        return $option;
    }

    /**
     * Create a choice list option
     *
     * It accepts an array consisting of any of the listed values. NULL values are not allowed.
     *
     * @return LeafOption
     */
    static function choiceList(string $name, ...$choices): LeafOption
    {
        $option = static::choice($name, ...$choices);
        $option->list = true;

        return $option;
    }

    /**
     * Create a node option
     *
     * It accepts an array with the given options.
     *
     * @return NodeOption
     */
    static function node(string $name, OptionDefinition ...$options): NodeOption
    {
        $children = [];

        foreach ($options as $childOption) {
            $children[$childOption->name] = $childOption;
        }

        return new NodeOption($name, $children);
    }

    /**
     * Create a node list option
     *
     * It accepts an array of arrays with the given options.
     *
     * @return NodeOption
     */
    static function nodeList(string $name, OptionDefinition ...$options): NodeOption
    {
        $option = static::node($name, ...$options);
        $option->list = true;

        return $option;
    }
}
