<?php declare(strict_types=1);

namespace Kuria\Options\Integration;

use Kuria\Options\Node;
use Kuria\Options\Resolver;
use Kuria\Options\Exception\ResolverException;

/**
 * Add options resolver support to a class
 *
 * The configured options resolver is cached per class and reused by multiple instances.
 */
trait StaticOptionsTrait
{
    /** @var Resolver[]|null */
    private static $optionsResolverCache;

    /**
     * Create options resolver used by this class
     *
     * This is done only once per class (or until the cache is cleared).
     *
     * @see StaticOptionsTrait::clearOptionsResolverCache()
     */
    protected static function createOptionsResolver(): Resolver
    {
        return new Resolver();
    }

    /**
     * Define options used by this class
     *
     * This is done only once per class (or until the cache is cleared).
     *
     * @see StaticOptionsTrait::clearOptionsResolverCache()
     */
    abstract protected static function defineOptions(Resolver $resolver): void;

    /**
     * Resolve options
     *
     * @throws ResolverException on failure
     * @return Node|mixed
     */
    protected static function resolveOptions($options, array $context = [])
    {
        return self::getOptionsResolver()->resolve($options, $context);
    }

    private static function getOptionsResolver(): Resolver
    {
        if (!isset(self::$optionsResolverCache[static::class])) {
            $optionsResolver = static::createOptionsResolver();
            static::defineOptions($optionsResolver);

            return self::$optionsResolverCache[static::class] = $optionsResolver;
        }

        return self::$optionsResolverCache[static::class];
    }

    static function clearOptionsResolverCache(): void
    {
        self::$optionsResolverCache = null;
    }
}
