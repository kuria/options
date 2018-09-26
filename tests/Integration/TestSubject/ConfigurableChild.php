<?php declare(strict_types=1);

namespace Kuria\Options\Integration\TestSubject;

use Kuria\Options\Resolver;
use Kuria\Options\OptionFactory;

/**
 * @internal
 */
class ConfigurableChild extends Configurable
{
    static $defineOptionsCallCount = 0;

    protected static function defineOptions(Resolver $resolver): void
    {
        parent::defineOptions($resolver);

        $resolver->addOption(
            OptionFactory::int('level')->default(1)
        );
    }
}
