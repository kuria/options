<?php declare(strict_types=1);

namespace Kuria\Options\Error;

class InvalidChoiceError extends Error
{
    /** @var array */
    private $choices;

    /** @var bool */
    private $nullable;

    /** @var mixed */
    private $actualValue;

    function __construct(array $choices, bool $nullable, $actualValue)
    {
        $this->choices = $choices;
        $this->nullable = $nullable;
        $this->actualValue = $actualValue;
    }

    function getMessage(): string
    {
        return sprintf(
            '%s is expected, but got %s instead',
            $this->getChoicesString(),
            static::dump($this->actualValue)
        );
    }

    function getChoices(): array
    {
        return $this->choices;
    }

    function isNullable(): bool
    {
        return $this->nullable;
    }

    function getActualValue()
    {
        return $this->actualValue;
    }

    private function getChoicesString(): string
    {
        $choices = array_values($this->choices);

        if ($this->nullable) {
            $choices[] = null;
        }

        $choicesString = '';

        for ($i = 0, $last = count($choices) - 1; $i <= $last; ++$i) {
            if ($i > 0) {
                $choicesString .= $i < $last ? ', ' : ' or ';
            }

            $choicesString .= static::dump($choices[$i]);
        }

        return $choicesString;
    }
}
