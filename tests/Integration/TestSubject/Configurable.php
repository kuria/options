<?php declare(strict_types=1);

namespace Kuria\Options\Integration\TestSubject;

use Kuria\Options\Resolver;
use Kuria\Options\Integration\StaticOptionsTrait;
use Kuria\Options\Option;

/**
 * @internal
 */
class Configurable
{
    use StaticOptionsTrait {
        resolveOptions as public;
    }

    static $defineOptionsCallCount = 0;

    static $lastValidatorArgs = null;

    static function reset()
    {
        static::$defineOptionsCallCount = 0;
        static::$lastValidatorArgs = null;
        static::clearOptionsResolverCache();
    }

    protected static function defineOptions(Resolver $resolver): void
    {
        ++static::$defineOptionsCallCount;

        $resolver->addOption(
            Option::string('name')->validate(function () {
                static::$lastValidatorArgs = func_get_args();
            }),
            Option::int('score')->default(0)
        );
    }
}
