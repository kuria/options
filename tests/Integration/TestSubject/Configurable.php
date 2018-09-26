<?php declare(strict_types=1);

namespace Kuria\Options\Integration\TestSubject;

use Kuria\Options\Resolver;
use Kuria\Options\Integration\StaticOptionsTrait;
use Kuria\Options\OptionFactory;

/**
 * @internal
 */
class Configurable
{
    use StaticOptionsTrait {
        resolveOptions as public;
    }

    static $defineOptionsCallCount = 0;

    static function reset()
    {
        static::$defineOptionsCallCount = 0;
        static::clearOptionsResolverCache();
    }

    protected static function defineOptions(Resolver $resolver): void
    {
        ++static::$defineOptionsCallCount;

        $resolver->addOption(
            OptionFactory::string('name'),
            OptionFactory::int('score')->default(0)
        );
    }
}
