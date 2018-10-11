<?php declare(strict_types=1);

namespace Kuria\Options\Option;

abstract class OptionDefinition
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
    public $normalizers;

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
     * Append a normalizer
     *
     * Callback signature: ($value): mixed
     *
     * - it should return the normalized value
     * - it should throw NormalizerException on failure.
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

    /**
     * Append a validator
     *
     * Callback signature: ($value): mixed
     *
     * - it should return NULL or an empty array if there no errors
     * - it should return errors as a string, an array of strings or Error instances
     *
     * @see \Kuria\Options\Error\Error
     *
     * @return $this
     */
    function validate(callable $validator): self
    {
        $this->validators[] = $validator;

        return $this;
    }
}
