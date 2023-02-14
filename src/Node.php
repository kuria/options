<?php declare(strict_types=1);

namespace Kuria\Options;

use Kuria\Options\Exception\CircularDependencyException;
use Kuria\Options\Exception\UnknownOptionException;
use Kuria\Options\Helper\NodeHelper;

/**
 * Resolved options node
 */
class Node implements \Countable, \ArrayAccess, \IteratorAggregate
{
    /** @var array */
    private $path;

    /** @var array */
    private $options;

    /** @var callable[] */
    private $lazyOptions;

    /** @var array|null */
    private $lazyOptionCallMap;

    /** @var array */
    private $context;

    /**
     * @param callable[] $lazyOptions
     */
    function __construct(array $path, array $options, array $lazyOptions = [], array $context = [])
    {
        $this->path = $path;
        $this->options = $options; // assign by value
        $this->lazyOptions = $lazyOptions;
        $this->context = $context;
    }

    function __debugInfo()
    {
        return $this->toArray();
    }

    function getPath(): array
    {
        return $this->path;
    }

    function toArray(): array
    {
        $array = [];
        $queue = [[$this, &$array]];
        $last = 0;

        while ($last >= 0) {
            // pop an item off the queue
            $item = &$queue[$last];
            unset($queue[$last--]);

            // resolve lazy options
            $item[0]->resolveLazyOptions();

            // iterate options
            foreach ($item[0]->options as $key => $value) {
                if (is_array($value)) {
                    // array - possibly a node list, iterate the first level only
                    $item[1][$key] = [];

                    foreach ($value as $listKey => $listValue) {
                        if ($listValue instanceof self) {
                            // queue list item node
                            $item[1][$key][$listKey] = [];
                            $queue[++$last] = [$listValue, &$item[1][$key][$listKey]];
                        } else {
                            // other
                            $item[1][$key][$listKey] = $listValue;
                        }
                    }
                } elseif ($value instanceof self) {
                    // queue node
                    $item[1][$key] = [];
                    $queue[++$last] = [$value, &$item[1][$key]];
                } else {
                    // other
                    $item[1][$key] = $value;
                }
            }
        }

        return $array;
    }

    function count(): int
    {
        return count($this->options) + count($this->lazyOptions);
    }

    function offsetExists($offset): bool
    {
        return key_exists($offset, $this->options) || isset($this->lazyOptions[$offset]);
    }

    #[\ReturnTypeWillChange]
    function &offsetGet($offset)
    {
        if (key_exists($offset, $this->options)) {
            return $this->options[$offset];
        }

        if (isset($this->lazyOptions[$offset])) {
            $this->resolveLazyOption($offset);

            return $this->options[$offset];
        }

        throw new UnknownOptionException(sprintf(
            'Unknown option %s, known options are: %s',
            NodeHelper::formatPath(array_merge($this->path, [$offset])),
            implode(', ', array_merge(array_keys($this->options), array_keys($this->lazyOptions)))
        ));
    }

    function offsetSet($offset, $value): void
    {
        $this->options[$offset] = $value;
        unset($this->lazyOptions[$offset]);
    }

    function offsetUnset($offset): void
    {
        unset($this->options[$offset], $this->lazyOptions[$offset]);
    }

    function getIterator(): \Traversable
    {
        $this->resolveLazyOptions();

        yield from $this->options;
    }

    private function resolveLazyOption($name): void
    {
        if (isset($this->lazyOptionCallMap[$name])) {
            throw new CircularDependencyException(sprintf(
                'Recursive dependency detected%s between lazy options %s->%s',
                $this->path ? sprintf(' at %s', NodeHelper::formatPath($this->path)) : '',
                implode('->', array_keys($this->lazyOptionCallMap)),
                $name
            ));
        }

        $this->lazyOptionCallMap[$name] = true;

        try {
            $this->options[$name] = $this->lazyOptions[$name]($this, ...$this->context);
            unset($this->lazyOptions[$name]);
        } finally {
            unset($this->lazyOptionCallMap[$name]);
        }
    }

    private function resolveLazyOptions(): void
    {
        if ($this->lazyOptions) {
            foreach (array_keys($this->lazyOptions) as $name) {
                $this->resolveLazyOption($name);
            }
        }
    }
}
