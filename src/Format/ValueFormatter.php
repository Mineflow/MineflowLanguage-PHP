<?php
declare(strict_types=1);

namespace Mineflow\Language\Format;

use Mineflow\Language\Value;

/**
 * Converts values to text. Used for template interpolation and joining lists.
 * Set a custom format with LanguageBuilder::withFormatter().
 */
interface ValueFormatter {
    public function format(Value $value): string;
}
