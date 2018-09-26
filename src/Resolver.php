<?php declare(strict_types=1);

namespace Kuria\Options;

use Kuria\Options\Error\EmptyValueError;
use Kuria\Options\Error\InvalidChoiceError;
use Kuria\Options\Error\InvalidTypeError;
use Kuria\Options\Error\MissingOptionError;
use Kuria\Options\Error\UnknownOptionError;
use Kuria\Options\Exception\NormalizerException;
use Kuria\Options\Exception\ResolverException;
use Kuria\Options\Option\Option;
use Kuria\Options\Option\LeafOption;
use Kuria\Options\Option\NodeOption;

class Resolver
{
    /** @var callable[] */
    private const BASIC_TYPE_VALIDATORS = [
        'bool' => 'is_bool',
        'int' => 'is_int',
        'float' => 'is_float',
        'number' => [__CLASS__, 'isNumber'],
        'numeric' => 'is_numeric',
        'string' => 'is_string',
        'array' => 'is_array',
        'iterable' => 'is_iterable',
        'object' => 'is_object',
        'resource' => 'is_resource',
        'scalar' => 'is_scalar',
        'callable' => 'is_callable',
    ];

    /** @var Option[] name-indexed */
    private $options = [];

    /** @var bool */
    private $ignoreUnknown = false;

    /**
     * See if an option exists
     */
    function hasOption(string $optionName): bool
    {
        return isset($this->options[$optionName]);
    }

    /**
     * Get an option
     *
     * Returns NULL if there is no such option.
     */
    function getOption(string $optionName): ?Option
    {
        return $this->options[$optionName] ?? null;
    }

    /**
     * Get all options
     *
     * @return Option[]
     */
    function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Add one or more options
     */
    function addOption(Option ...$options): void
    {
        foreach ($options as $option) {
            $this->options[$option->name] = $option;
        }
    }

    /**
     * Remove an option
     */
    function removeOption(string $optionName): void
    {
        unset($this->options[$optionName]);
    }

    /**
     * Remove all options
     */
    function clearOptions(): void
    {
        $this->options = [];
    }

    /**
     * See whether unknown options are ignored
     */
    function isIgnoringUnknown(): bool
    {
        return $this->ignoreUnknown;
    }

    /**
     * Set whether to ignore unknown options
     */
    function setIgnoreUnknown(bool $ignoreUnknown): void
    {
        $this->ignoreUnknown = $ignoreUnknown;
    }

    /**
     * Resolve the given data
     *
     * @throws ResolverException on failure
     */
    function resolve($data): Node
    {
        $errors = [];

        /** @var ResolverNode[] $queue */
        $queue = [new ResolverNode(new NodeOption('root', $this->options), [], $data, $errors)];
        $last = 0;
        $nodeValidations = [];
        $nodeListValidations = [];

        while ($last >= 0) {
            // pop a resolver node off the queue
            $node = $queue[$last];
            unset($queue[$last--]);

            // validate ref
            if (!is_array($node->ref)) {
                $node->addError(new InvalidTypeError('array', false, $node->ref));

                continue;
            }

            // resolve children
            $lazyOptions = [];

            foreach ($node->option->children as $option) {
                if (key_exists($option->name, $node->ref)) {
                    // handle value
                    if ($option instanceof LeafOption) {
                        if (
                            !$this->validateLeafOptionValue($node, $option)
                            || !$this->normalizeLeafOptionValue($node, $option)
                        ) {
                            continue;
                        }
                    } elseif ($option instanceof NodeOption) {
                        if (!$this->validateNodeOptionValue($node, $option)) {
                            continue;
                        }
                    }
                } elseif ($option->required) {
                    // missing required option
                    $node->addError(new MissingOptionError(), $option->name);

                    continue;
                } else {
                    // use default
                    if ($option instanceof LeafOption && $option->defaultIsLazy()) {
                        $lazyOptions[$option->name] = $option->default;
                    } else {
                        $node->ref[$option->name] = $option->default;
                    }
                }

                // queue child nodes
                if ($option instanceof NodeOption && isset($node->ref[$option->name])) {
                    if ($option->list) {
                        // queue node list validators
                        if ($option->validators) {
                            $nodeListValidations[] = [$node, $option->name, $option->validators];
                        }

                        foreach ($node->ref[$option->name] as $listKey => &$listItem) {
                            $queue[++$last] = new ResolverNode(
                                $option,
                                $node->getChildPath($option->name, $listKey),
                                $listItem,
                                $errors,
                                true
                            );
                        }
                    } else {
                        $queue[++$last] = new ResolverNode(
                            $option,
                            $node->getChildPath($option->name),
                            $node->ref[$option->name],
                            $errors
                        );
                    }
                }
            }

            // detect unknown options
            if (!$this->ignoreUnknown) {
                foreach (array_diff_key($node->ref, $node->option->children) as $unknownKey => $_) {
                    $node->addError(new UnknownOptionError(), $unknownKey);
                }
            }

            // queue node validators
            if (!$node->isListItem && $node->option->validators) {
                $nodeValidations[] = [$node, $node->option->validators];
            }

            // replace resolver node reference with a node
            $node->ref = new Node($node->path, $node->ref, $lazyOptions);
        }

        // run node validations if there are no errors
        if (empty($errors)) {
            foreach ($nodeValidations as $validation) {
                foreach ($validation[1] as $validator) {
                    if ($validatorErrors = $validator($validation[0]->ref)) {
                        $validation[0]->addErrors($validatorErrors);
                        break;
                    }
                }
            }

            foreach ($nodeListValidations as $validation) {
                foreach ($validation[2] as $validator) {
                    if ($validatorErrors = $validator($validation[0]->ref[$validation[1]])) {
                        $validation[0]->addErrors($validatorErrors, $validation[1]);
                        break;
                    }
                }
            }
        }

        if ($errors) {
            throw new ResolverException($errors);
        }

        // at this point, the $data variable has been replaced with a node
        /** @var Node $data */

        return $data;
    }

    private function validateNodeOptionValue(ResolverNode $node, NodeOption $option): bool
    {
        $value = $node->ref[$option->name];

        // null check
        if ($value === null) {
            if ($option->nullable) {
                return true;
            } else {
                $node->addError(new InvalidTypeError('array', false, null), $option->name);

                return false;
            }
        }

        // ensure array value if this is a node list
        if ($option->list && !is_array($value)) {
            $node->addError(new InvalidTypeError('array', $option->nullable, $value), $option->name);

            return false;
        }

        // validate emptiness
        if (!$option->allowEmpty && empty($value)) {
            $node->addError(new EmptyValueError($option->nullable, $value), $option->name);

            return false;
        }

        return true;
    }

    private function validateLeafOptionValue(ResolverNode $node, LeafOption $option): bool
    {
        $value = $node->ref[$option->name];

        // determine expected type
        if ($option->list) {
            $expectedType = 'array';
        } else {
            $expectedType = $option->type;
        }

        // null check
        if ($value === null) {
            if ($option->nullable) {
                return true;
            } else {
                $node->addError(new InvalidTypeError($expectedType, false, null), $option->name);

                return false;
            }
        }

        // validate type
        if ($expectedType !== null && !$this->checkValueType($value, $expectedType)) {
            $node->addError(new InvalidTypeError($expectedType, $option->nullable, $value), $option->name);

            return false;
        }

        // validate emptiness
        if (!$option->allowEmpty && empty($value)) {
            $node->addError(new EmptyValueError($option->nullable, $value), $option->name);

            return false;
        }

        if ($option->list) {
            // validate list items
            $valid = true;

            foreach ($value as $listKey => $listItem) {
                if ($listItem === null || $option->type !== null && !$this->checkValueType($listItem, $option->type)) {
                    $node->addError(new InvalidTypeError($option->type, false, $listItem), $option->name, $listKey);
                    $valid = false;
                } elseif ($option->choices !== null && !in_array($listItem, $option->choices, true)) {
                    $node->addError(new InvalidChoiceError($option->choices, false, $listItem), $option->name, $listKey);
                    $valid = false;
                } elseif (!$option->allowEmpty && empty($listItem)) {
                    $node->addError(new EmptyValueError(false, $listItem), $option->name, $listKey);
                    $valid = false;
                }
            }

            if (!$valid) {
                return false;
            }
        } else {
            // validate single value
            if ($option->choices !== null && !in_array($value, $option->choices, true)) {
                $node->addError(new InvalidChoiceError($option->choices, $option->nullable, $value), $option->name);

                return false;
            }
        }

        // run validators
        if ($option->validators) {
            foreach ($option->validators as $validator) {
                if ($errors = $validator($value)) {
                    $node->addErrors($errors, $option->name);

                    return false;
                }
            }
        }

        return true;
    }

    private function normalizeLeafOptionValue(ResolverNode $node, LeafOption $option): bool
    {
        if ($option->normalizers) {
            try {
                foreach ($option->normalizers as $normalizer) {
                    $node->ref[$option->name] = $normalizer($node->ref[$option->name]);
                }
            } catch (NormalizerException $e) {
                $node->addErrors($e->getErrors(), $option->name);

                return false;
            }
        }

        return true;
    }

    private function checkValueType($value, string $expectedType): bool
    {
        $validator = static::BASIC_TYPE_VALIDATORS[$expectedType] ?? null;

        if ($validator !== null) {
            // basic type
            return $validator($value);
        } else {
            // instance of a class
            return $value instanceof $expectedType;
        }
    }

    private static function isNumber($value): bool
    {
        return is_int($value) || is_float($value);
    }
}
