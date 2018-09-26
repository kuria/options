<?php declare(strict_types=1);

namespace Kuria\Options\Option;

/**
 * Base option definition
 */
abstract class Option
{
    /** @var string read-only */
    public $name;

    /** @var bool read-only */
    public $required = true;

    /** @var mixed read-only */
    public $default;

    /** @var bool read-only */
    public $nullable = false;

    /** @var bool read-only */
    public $allowEmpty = true;

    /** @var bool read-only */
    public $list = false;

    /** @var callable[]|null read-only */
    public $validators;

    function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Make the option required
     *
     * @return $this
     */
    function required(): self
    {
        $this->required = true;
        $this->default = null;

        return $this;
    }

    /**
     * Allow a NULL value
     *
     * @return $this
     */
    function nullable(): self
    {
        $this->nullable = true;

        return $this;
    }

    /**
     * Disallow a NULL value
     *
     * @return $this
     */
    function notNullable(): self
    {
        $this->nullable = false;

        return $this;
    }

    /**
     * Allow an empty value
     *
     * @return $this
     */
    function allowEmpty(): self
    {
        $this->allowEmpty = true;

        return $this;
    }

    /**
     * Disallow an empty value
     *
     * @return $this
     */
    function notEmpty(): self
    {
        $this->allowEmpty = false;

        return $this;
    }

    /**
     * Append a validator
     *
     * Callback signature: ($value): ?Error[]
     *
     * @return $this
     */
    function validate(callable $validator): self
    {
        $this->validators[] = $validator;

        return $this;
    }
}
