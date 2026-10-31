<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax\Node;

final readonly class NumberNode extends Node {
    public function __construct(public float $value, int $start, int $end) {
        parent::__construct($start, $end);
    }

    /**
     * Returns the number the word represents, or null if it cannot be read as a number.
     */
    public static function parse(string $text): ?float {
        return is_numeric($text) ? (float)$text : null;
    }
}
