<?php declare(strict_types=1);

namespace Kuria\Options\Traits;

use Kuria\Options\Option\Option;
use Kuria\Options\Option\LeafOption;
use Kuria\Options\Option\NodeOption;

trait OptionAssertionTrait
{
    abstract static function assertLooselyIdentical($expected, $actual, bool $canonicalizeKeys = false, string $message = ''): void;

    static function assertOption(array $expectedProps, Option $option): void
    {
        $expectedProps += [
            'nullable' => false,
            'allowEmpty' => true,
            'list' => false,
            'validators' => null,
        ];

        if ($option instanceof NodeOption) {
            $expectedProps += [
                'required' => false,
                'default' => [],
                'children' => [],
            ];
        } elseif ($option instanceof LeafOption) {
            $expectedProps += [
                'required' => true,
                'default' => null,
                'type' => null,
                'choices' => null,
                'normalizers' => null,
            ];
        }

        foreach (get_object_vars($option) as $property => $value) {
            $actualProps[$property] = $value;
        }

        ksort($expectedProps);
        ksort($actualProps);

        static::assertLooselyIdentical($expectedProps, $actualProps);
    }
}
