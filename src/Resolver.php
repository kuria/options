<?php declare(strict_types=1);

namespace Kuria\Options;

use Kuria\Options\Error\EmptyValueError;
use Kuria\Options\Error\Error;
use Kuria\Options\Error\InvalidChoiceError;
use Kuria\Options\Error\InvalidOptionError;
use Kuria\Options\Error\InvalidTypeError;
use Kuria\Options\Error\MissingOptionError;
use Kuria\Options\Error\UnknownOptionError;
use Kuria\Options\Exception\NormalizerException;
use Kuria\Options\Exception\ResolverException;
use Kuria\Options\Option\OptionDefinition;
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

    private const NODE_OP_NORMALIZE = 0;
    private const NODE_OP_NORMALIZE_LIST = 1;
    private const NODE_OP_VALIDATE = 2;
    private const NODE_OP_VALIDATE_LIST = 3;

    /** @var bool */
    private $ignoreUnknown = false;

    /** @var NodeOption */
    private $root;

    function __construct()
    {
        $this->root = new NodeOption('root', []);
    }

    /**
     * See if an option exists
     */
    function hasOption(string $optionName): bool
    {
        return isset($this->root->children[$optionName]);
    }

    /**
     * Get an option
     *
     * Returns NULL if there is no such option.
     */
    function getOption(string $optionName): ?OptionDefinition
    {
        return $this->root->children[$optionName] ?? null;
    }

    /**
     * Get all options
     *
     * @return OptionDefinition[] name-indexed
     */
    function getOptions(): array
    {
        return $this->root->children;
    }

    /**
     * Add one or more options
     */
    function addOption(OptionDefinition ...$options): void
    {
        foreach ($options as $option) {
            $this->root->children[$option->name] = $option;
        }
    }

    /**
     * Append a root normalizer
     *
     * @see OptionDefinition::normalize()
     */
    function addNormalizer(callable $normalizer): void
    {
        $this->root->normalizers[] = $normalizer;
    }

    /**
     * Append a root validator
     *
     * @see OptionDefinition::validate()
     */
    function addValidator(callable $validator): void
    {
        $this->root->validators[] = $validator;
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
     * Returns a Node instance by default. This can be changed by normalizers.
     *
     * @see Resolver::addNormalizer()
     *
     * @param array $data the array to resolve
     * @param array $context list of additional arguments to pass to validators and normalizers
     * @throws ResolverException on failure
     * @return Node|mixed
     */
    function resolve($data, array $context = [])
    {
        $errors = [];

        /** @var ResolverNode[] $queue */
        $queue = [new ResolverNode($this->root, [], $data, $errors)];
        $last = 0;
        $nodeOpsLifoQueue = []; // last in, first out

        while ($last >= 0) {
            // pop a resolver node off the queue
            $node = $queue[$last];
            unset($queue[$last--]);

            // validate ref
            if (!is_array($node->ref)) {
                $node->addError(new InvalidTypeError('array', false, $node->ref));

                continue;
            }

            // queue node normalizers and validators
            if (!$node->isListItem) {
                if ($node->option->validators) {
                    $nodeOpsLifoQueue[] = [self::NODE_OP_VALIDATE, $node, $node->option->validators];
                }

                if ($node->option->normalizers) {
                    $nodeOpsLifoQueue[] = [self::NODE_OP_NORMALIZE, $node, $node->option->normalizers];
                }
            }

            // resolve children
            $lazyOptions = [];

            foreach ($node->option->children as $option) {
                if (key_exists($option->name, $node->ref)) {
                    // handle value
                    if ($option instanceof LeafOption) {
                        if (!$this->resolveLeafOptionValue($node, $option, $context)) {
                            continue;
                        }
                    } elseif ($option instanceof NodeOption) {
                        if (!$this->resolveNodeOptionValue($node, $option)) {
                            continue;
                        }
                    } else {
                        throw new \LogicException('Unexpected option type'); // @codeCoverageIgnore
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
                        // queue node list normalizers and validators
                        if ($option->validators) {
                            $nodeOpsLifoQueue[] = [self::NODE_OP_VALIDATE_LIST, $node, $option->name, $option->validators];
                        }

                        if ($option->normalizers) {
                            $nodeOpsLifoQueue[] = [self::NODE_OP_NORMALIZE_LIST, $node, $option->name, $option->normalizers];
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
            // replace resolver node reference with a node
            $node->ref = new Node($node->path, $node->ref, $lazyOptions, $context);
        }

        // run pending node operations if there are no errors
        if (!$errors) {
            $this->finalizeNodes($nodeOpsLifoQueue, $context);
        }

        if ($errors) {
            throw new ResolverException($errors);
        }

        // at this point, the $data variable has been replaced with a node (or a normalized value)

        return $data;
    }

    private function finalizeNodes(array &$nodeOpsLifoQueue, array $context): void
    {
        while ($op = array_pop($nodeOpsLifoQueue)) {
            switch ($op[0]) {
                case self::NODE_OP_NORMALIZE:
                    // opt: 1 => node, 2 => normalizers
                    try {
                        foreach ($op[2] as $normalizer) {
                            $op[1]->ref = $normalizer($op[1]->ref, ...$context);
                        }
                    } catch (NormalizerException $e) {
                        $op[1]->addErrors($e->getErrors());

                        return;
                    }
                    break;

                case self::NODE_OP_NORMALIZE_LIST:
                    // opt: 1 => node, 2 => optionName, 3 => normalizers
                    try {
                        foreach ($op[3] as $normalizer) {
                            $op[1]->ref[$op[2]] = $normalizer($op[1]->ref[$op[2]], ...$context);
                        }
                    } catch (NormalizerException $e) {
                        $op[1]->addErrors($e->getErrors(), $op[2]);

                        return;
                    }
                    break;

                case self::NODE_OP_VALIDATE:
                    // opt: 1 => node, 2 => validators
                    foreach ($op[2] as $validator) {
                        if ($validatorErrors = $validator($op[1]->ref, ...$context)) {
                            $op[1]->addErrors($this->handleValidatorErrors($validatorErrors));
                            break;
                        }
                    }
                    break;

                case self::NODE_OP_VALIDATE_LIST:
                    // opt: 1 => node, 2 => optionName, 3 => validators
                    foreach ($op[3] as $validator) {
                        if ($validatorErrors = $validator($op[1]->ref[$op[2]], ...$context)) {
                            $op[1]->addErrors($this->handleValidatorErrors($validatorErrors), $op[2]);
                            break;
                        }
                    }
                    break;

                default:
                    throw new \LogicException('Unexpected node operation type'); // @codeCoverageIgnore
            }
        }
    }

    private function resolveLeafOptionValue(ResolverNode $node, LeafOption $option, array $context): bool
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

        // run normalizers
        if ($option->normalizers) {
            try {
                foreach ($option->normalizers as $normalizer) {
                    $value = $normalizer($value, ...$context);
                }
            } catch (NormalizerException $e) {
                $node->addErrors($e->getErrors(), $option->name);

                return false;
            }

            $node->ref[$option->name] = $value;
        }

        // run validators
        if ($option->validators) {
            foreach ($option->validators as $validator) {
                if ($errors = $validator($value, ...$context)) {
                    $node->addErrors($this->handleValidatorErrors($errors), $option->name);

                    return false;
                }
            }
        }

        return true;
    }

    private function resolveNodeOptionValue(ResolverNode $node, NodeOption $option): bool
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

    /**
     * @return Error[]
     */
    private function handleValidatorErrors($errors): array
    {
        $errorObjects = [];

        foreach ((array) $errors as $error) {
            $errorObjects[] = $error instanceof Error ? $error : new InvalidOptionError((string) $error);
        }

        return $errorObjects;
    }

    private static function isNumber($value): bool
    {
        return is_int($value) || is_float($value);
    }
}
