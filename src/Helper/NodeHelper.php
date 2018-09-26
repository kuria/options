<?php declare(strict_types=1);

namespace Kuria\Options\Helper;

abstract class NodeHelper
{
    static function formatPath(array $path): string
    {
        $output = '';

        $itemCounter = 0;

        foreach ($path as $item) {
            if ($itemCounter > 0) {
                $output .= '[';
            }

            $output .= $item;

            if ($itemCounter > 0) {
                $output .= ']';
            }

            ++$itemCounter;
        }

        return $output;
    }
}
